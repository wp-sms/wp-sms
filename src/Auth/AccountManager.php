<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Auth\AuthSession;
use WSms\Enums\EnrollmentTiming;
use WSms\Enums\EventType;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;

defined('ABSPATH') || exit;

class AccountManager
{
    public const PLACEHOLDER_EMAIL_DOMAIN = 'noreply.wsms.local';

    private ?array $settings = null;

    public function __construct(
        private AuditLogger $auditLogger,
        private OtpGenerator $otpGenerator,
        private MfaManager $mfaManager,
        private AuthSession $authSession,
    ) {
    }

    public static function isPlaceholderEmail(string $email): bool
    {
        return str_ends_with($email, '@' . self::PLACEHOLDER_EMAIL_DOMAIN);
    }

    /**
     * Get the raw verification state for a user's email and phone channels.
     *
     * @return array{email: array{has: bool, verified: bool}, phone: array{has: bool, verified: bool}}
     */
    public static function getUserVerificationState(int $userId): array
    {
        $userEmail = get_userdata($userId)?->user_email ?? '';

        return [
            'email' => [
                'has'      => !empty($userEmail) && !self::isPlaceholderEmail($userEmail),
                'verified' => (bool) get_user_meta($userId, 'wsms_email_verified', true),
            ],
            'phone' => [
                'has'      => !empty(get_user_meta($userId, 'wsms_phone', true)),
                'verified' => (bool) get_user_meta($userId, 'wsms_phone_verified', true),
            ],
        ];
    }

    private static function generatePlaceholderEmail(): string
    {
        return bin2hex(random_bytes(5)) . '@' . self::PLACEHOLDER_EMAIL_DOMAIN;
    }

    private function getSettings(): array
    {
        return $this->settings ??= get_option('wsms_auth_settings', []);
    }

    /**
     * Register a new user.
     *
     * @return array{success: bool, user_id?: int, mfa_required?: bool, error?: string, message: string}
     */
    public function registerUser(array $data): array
    {
        $settings = $this->getSettings();
        $requiredFields = $settings['registration_fields'] ?? ['email', 'password'];

        // Channel-based requirement checks (default to required for backward compat).
        $emailRequired = $settings['email']['required_at_signup'] ?? true;
        if ($emailRequired && empty($data['email'])) {
            return ['success' => false, 'error' => 'missing_email', 'message' => 'Email is required.'];
        }

        $passwordRequired = $settings['password']['required_at_signup'] ?? true;
        if ($passwordRequired && empty($data['password'])) {
            return ['success' => false, 'error' => 'missing_password', 'message' => 'Password is required.'];
        }

        // Enforce phone.required_at_signup.
        if (!empty($settings['phone']['required_at_signup']) && empty($data['phone'])) {
            return ['success' => false, 'error' => 'missing_phone', 'message' => 'Phone number is required.'];
        }

        // Enforce registration_fields for first_name/last_name.
        if (in_array('first_name', $requiredFields, true) && empty($data['first_name'])) {
            return ['success' => false, 'error' => 'missing_first_name', 'message' => 'First name is required.'];
        }

        if (in_array('last_name', $requiredFields, true) && empty($data['last_name'])) {
            return ['success' => false, 'error' => 'missing_last_name', 'message' => 'Last name is required.'];
        }

        $email = sanitize_email($data['email'] ?? '');
        $isPlaceholder = false;

        if (empty($email) && !$emailRequired) {
            $email = self::generatePlaceholderEmail();
            $isPlaceholder = true;
        }

        if (!empty($data['email']) && !empty($email) && !is_email($email)) {
            return ['success' => false, 'error' => 'invalid_email', 'message' => 'Invalid email address.'];
        }

        $username = !empty($data['username'])
            ? sanitize_user($data['username'])
            : ($isPlaceholder
                ? sanitize_user(!empty($data['phone']) ? $data['phone'] : strtok($email, '@'))
                : $email);

        $userdata = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $data['password'] ?? bin2hex(random_bytes(16)),
        ];

        if (!empty($data['display_name'])) {
            $userdata['display_name'] = sanitize_text_field($data['display_name']);
        }

        if (!empty($data['first_name'])) {
            $userdata['first_name'] = sanitize_text_field($data['first_name']);
        }

        if (!empty($data['last_name'])) {
            $userdata['last_name'] = sanitize_text_field($data['last_name']);
        }

        $userId = wp_insert_user($userdata);

        if (is_wp_error($userId)) {
            return [
                'success' => false,
                'error'   => $userId->get_error_code(),
                'message' => $userId->get_error_message(),
            ];
        }

        if ($isPlaceholder) {
            update_user_meta($userId, 'wsms_email_placeholder', '1');
        }

        $pendingVerifications = [];

        // Store phone meta if provided.
        if (!empty($data['phone'])) {
            $phone = sanitize_text_field($data['phone']);
            update_user_meta($userId, 'wsms_phone', $phone);

            if (!empty($settings['phone']['verify_at_signup'])) {
                $this->createPhoneVerification($userId, $phone);
                $pendingVerifications[] = ['type' => 'phone', 'status' => 'pending'];
            }
        }

        // Generate and send email verification only when required (skip for placeholder emails).
        if (!empty($email) && !$isPlaceholder && !empty($settings['email']['verify_at_signup'])) {
            if ($this->emailUsesOtp()) {
                $this->createEmailOtpVerification($userId, $email);
            } else {
                $this->createVerification($userId, 'email_verify', $email);
            }
            $pendingVerifications[] = ['type' => 'email', 'status' => 'pending'];
        }

        $this->auditLogger->log(EventType::Register, 'success', $userId, [
            'method' => 'registration',
        ]);

        $result = [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Registration successful.',
        ];

        // Create registration session token for pending verifications.
        if (!empty($pendingVerifications)) {
            $result['pending_verifications'] = $pendingVerifications;
            $result['registration_token'] = $this->authSession->create(
                $userId,
                'registration',
                'registration_verify',
            );
        }

        $timing = EnrollmentTiming::tryFrom($settings['enrollment_timing'] ?? 'voluntary');

        if ($timing === EnrollmentTiming::OnRegistration) {
            $result['mfa_required'] = true;
        }

        return $result;
    }

    /**
     * Initiate a password reset. Always succeeds (anti-enumeration).
     */
    public function initiatePasswordReset(string $email): void
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return;
        }

        $this->createVerification($user->ID, 'password_reset', $email);

        $this->auditLogger->log(EventType::PasswordResetRequest, 'success', $user->ID);
    }

    /**
     * Complete a password reset.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function completePasswordReset(string $token, string $newPassword): array
    {
        $verification = $this->consumeVerification($token, 'password_reset');

        if (is_array($verification)) {
            return $verification;
        }

        wp_set_password($newPassword, (int) $verification->user_id);

        $this->auditLogger->log(EventType::PasswordResetComplete, 'success', (int) $verification->user_id);

        return ['success' => true, 'message' => 'Password has been reset successfully.'];
    }

    /**
     * Verify an email address using a token.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function verifyEmail(string $token): array
    {
        $verification = $this->consumeVerification($token, 'email_verify');

        if (is_array($verification)) {
            return $verification;
        }

        $userId = (int) $verification->user_id;
        $this->markEmailVerified($userId, $verification->identifier);
        $this->auditLogger->log(EventType::EmailVerified, 'success', $userId);

        return ['success' => true, 'message' => 'Email verified successfully.'];
    }

    /**
     * Update user profile.
     *
     * @return array{success: bool, error?: string, message: string, phone_verification_required?: bool, email_verification_required?: bool}
     */
    public function updateProfile(int $userId, array $data): array
    {
        // Validate all inputs before writing anything.
        if (isset($data['email'])) {
            $newEmail = sanitize_email($data['email']);

            if (!is_email($newEmail)) {
                return ['success' => false, 'error' => 'invalid_email', 'message' => 'Invalid email address.'];
            }
        }

        $result = ['success' => true, 'message' => 'Profile updated.'];

        $userUpdate = ['ID' => $userId];

        if (isset($data['display_name'])) {
            $userUpdate['display_name'] = sanitize_text_field($data['display_name']);
        }

        if (isset($data['first_name'])) {
            $userUpdate['first_name'] = sanitize_text_field($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $userUpdate['last_name'] = sanitize_text_field($data['last_name']);
        }

        if (count($userUpdate) > 1) {
            wp_update_user($userUpdate);
        }

        if (isset($data['phone'])) {
            $phone = sanitize_text_field($data['phone']);
            update_user_meta($userId, 'wsms_phone', $phone);
            update_user_meta($userId, 'wsms_phone_verified', '0');
            $result['phone_verification_required'] = true;
        }

        if (isset($newEmail)) {
            update_user_meta($userId, 'wsms_pending_email', $newEmail);
            $this->createVerification($userId, 'email_verify', $newEmail);
            $result['email_verification_required'] = true;
        }

        return $result;
    }

    /**
     * Change user password.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = get_userdata($userId);

        if (!$user) {
            return ['success' => false, 'error' => 'user_not_found', 'message' => 'User not found.'];
        }

        if (!wp_check_password($currentPassword, $user->user_pass, $userId)) {
            return ['success' => false, 'error' => 'wrong_password', 'message' => 'Current password is incorrect.'];
        }

        wp_set_password($newPassword, $userId);
        wp_set_auth_cookie($userId, false);
        wp_set_current_user($userId);

        $this->auditLogger->log(EventType::PasswordChange, 'success', $userId);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }

    /**
     * Log out the current user.
     */
    public function logout(): void
    {
        $userId = get_current_user_id();

        $this->auditLogger->log(EventType::Logout, 'success', $userId ?: null);

        wp_logout();
    }

    /**
     * Verify a phone number using an OTP code.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function verifyPhone(int $userId, string $code): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND type = 'phone_verify' AND used_at IS NULL ORDER BY created_at DESC LIMIT 1",
            $userId,
        ));

        if (!$verification) {
            return ['success' => false, 'error' => 'no_verification', 'message' => 'No pending phone verification.'];
        }

        if (strtotime($verification->expires_at) < time()) {
            return ['success' => false, 'error' => 'expired', 'message' => 'Verification code has expired.'];
        }

        if ((int) $verification->attempts >= (int) $verification->max_attempts) {
            return ['success' => false, 'error' => 'max_attempts', 'message' => 'Too many attempts.'];
        }

        $wpdb->update($table, ['attempts' => (int) $verification->attempts + 1], ['id' => $verification->id]);

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            return ['success' => false, 'error' => 'invalid_code', 'message' => 'Invalid verification code.'];
        }

        $wpdb->update($table, ['used_at' => gmdate('Y-m-d H:i:s')], ['id' => $verification->id]);
        update_user_meta($userId, 'wsms_phone_verified', '1');

        $this->auditLogger->log(EventType::PhoneVerified, 'success', $userId);

        return ['success' => true, 'message' => 'Phone verified successfully.'];
    }

    /**
     * Resend phone verification OTP.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function resendPhoneVerification(int $userId): array
    {
        $phone = get_user_meta($userId, 'wsms_phone', true);

        if (empty($phone)) {
            return ['success' => false, 'error' => 'no_phone', 'message' => 'No phone number on file.'];
        }

        $settings = $this->getSettings();
        $cooldown = (int) ($settings['phone']['cooldown'] ?? 60);

        if ($this->isVerificationOnCooldown($userId, 'phone_verify', $cooldown)) {
            return ['success' => false, 'error' => 'cooldown', 'message' => 'Please wait before requesting a new code.'];
        }

        $this->invalidateVerifications($userId, 'phone_verify');
        $this->createPhoneVerification($userId, $phone);

        return ['success' => true, 'message' => 'New verification code sent.'];
    }

    /**
     * Resend email verification link.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function resendEmailVerification(int $userId): array
    {
        $email = get_userdata($userId)?->user_email;

        if (empty($email) || self::isPlaceholderEmail($email)) {
            return ['success' => false, 'error' => 'no_email', 'message' => 'No email address on file.'];
        }

        $settings = $this->getSettings();
        $cooldown = (int) ($settings['email']['cooldown'] ?? 60);

        if ($this->isVerificationOnCooldown($userId, 'email_verify', $cooldown)) {
            return ['success' => false, 'error' => 'cooldown', 'message' => 'Please wait before requesting a new email.'];
        }

        $this->invalidateVerifications($userId, 'email_verify');

        if ($this->emailUsesOtp()) {
            $this->createEmailOtpVerification($userId, $email);
        } else {
            $this->createVerification($userId, 'email_verify', $email);
        }

        return ['success' => true, 'message' => 'Verification email resent.'];
    }

    /**
     * Get the verification status for a user.
     *
     * @return array{pending_verifications: array, all_verified: bool}
     */
    public function getVerificationStatus(int $userId): array
    {
        $state = self::getUserVerificationState($userId);
        $pending = [];

        if ($state['phone']['has'] && !$state['phone']['verified']) {
            $pending[] = ['type' => 'phone', 'status' => 'pending'];
        }

        if ($state['email']['has'] && !$state['email']['verified']) {
            $pending[] = ['type' => 'email', 'status' => 'pending'];
        }

        return [
            'pending_verifications' => $pending,
            'all_verified'          => empty($pending),
        ];
    }

    /**
     * Send a fresh verification challenge for login-time enforcement.
     */
    public function sendVerificationChallenge(int $userId, string $type): void
    {
        if ($type === 'phone') {
            $phone = get_user_meta($userId, 'wsms_phone', true);
            if (!empty($phone)) {
                $this->invalidateVerifications($userId, 'phone_verify');
                $this->createPhoneVerification($userId, $phone);
            }
        } elseif ($type === 'email') {
            $email = get_userdata($userId)?->user_email;
            if (!empty($email) && !self::isPlaceholderEmail($email)) {
                $this->invalidateVerifications($userId, 'email_verify');
                if ($this->emailUsesOtp()) {
                    $this->createEmailOtpVerification($userId, $email);
                } else {
                    $this->createVerification($userId, 'email_verify', $email);
                }
            }
        }
    }

    /**
     * Create a phone verification record and send OTP via SMS.
     */
    private function createPhoneVerification(int $userId, string $phone): void
    {
        global $wpdb;

        $settings = $this->getSettings();
        $phoneSettings = $settings['phone'] ?? [];
        $codeLength = (int) ($phoneSettings['code_length'] ?? 6);
        $expiry = (int) ($phoneSettings['expiry'] ?? 300);
        $maxAttempts = (int) ($phoneSettings['max_attempts'] ?? 5);

        $otp = $this->otpGenerator->generate($codeLength);
        do_action('wsms_otp_generated', $userId, $otp, 'phone_verify');
        $hashed = $this->otpGenerator->hash($otp);

        $wpdb->insert($wpdb->prefix . 'wsms_verifications', [
            'user_id'      => $userId,
            'type'         => 'phone_verify',
            'identifier'   => $phone,
            'code'         => $hashed,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        do_action('wsms_send_sms', $phone, sprintf('Your verification code is: %s', $otp));
    }

    /**
     * Whether the email channel is configured for OTP verification.
     */
    public function emailUsesOtp(): bool
    {
        $settings = $this->getSettings();
        $methods = (array) ($settings['email']['verification_methods'] ?? ['otp']);

        return in_array('otp', $methods, true);
    }

    /**
     * Create an email verification record and send OTP via email.
     */
    private function createEmailOtpVerification(int $userId, string $email): void
    {
        global $wpdb;

        $settings = $this->getSettings();
        $emailSettings = $settings['email'] ?? [];
        $codeLength = (int) ($emailSettings['code_length'] ?? 6);
        $expiry = (int) ($emailSettings['expiry'] ?? 600);
        $maxAttempts = (int) ($emailSettings['max_attempts'] ?? 5);

        $otp = $this->otpGenerator->generate($codeLength);
        do_action('wsms_otp_generated', $userId, $otp, 'email_verify');
        $hashed = $this->otpGenerator->hash($otp);

        $wpdb->insert($wpdb->prefix . 'wsms_verifications', [
            'user_id'      => $userId,
            'type'         => 'email_verify',
            'identifier'   => $email,
            'code'         => $hashed,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        $siteName = get_bloginfo('name');
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $subject = sprintf('[%s] Your verification code', $siteName);
        $message = sprintf(
            '<p>Your email verification code is:</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">%s</p>'
            . '<p>This code expires in %d minutes.</p>'
            . '<p>If you did not request this, please ignore this email.</p>',
            esc_html($otp),
            (int) ceil($expiry / 60),
        );

        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Verify an email OTP code (mirrors verifyPhone).
     */
    public function verifyEmailOtp(int $userId, string $code): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND type = 'email_verify' AND used_at IS NULL ORDER BY created_at DESC LIMIT 1",
            $userId,
        ));

        if (!$verification) {
            return ['success' => false, 'error' => 'no_verification', 'message' => 'No pending email verification.'];
        }

        if (strtotime($verification->expires_at) < time()) {
            return ['success' => false, 'error' => 'expired', 'message' => 'Verification code has expired.'];
        }

        if ((int) $verification->attempts >= (int) $verification->max_attempts) {
            return ['success' => false, 'error' => 'max_attempts', 'message' => 'Too many attempts.'];
        }

        $newAttempts = (int) $verification->attempts + 1;

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            $wpdb->update($table, ['attempts' => $newAttempts], ['id' => $verification->id]);

            return ['success' => false, 'error' => 'invalid_code', 'message' => 'Invalid verification code.'];
        }

        $wpdb->update($table, ['attempts' => $newAttempts, 'used_at' => gmdate('Y-m-d H:i:s')], ['id' => $verification->id]);
        $this->markEmailVerified($userId, $verification->identifier);
        $this->auditLogger->log(EventType::EmailVerified, 'success', $userId);

        return ['success' => true, 'message' => 'Email verified successfully.'];
    }

    /**
     * Mark email as verified and apply any pending email change.
     */
    private function markEmailVerified(int $userId, string $verifiedAddress): void
    {
        update_user_meta($userId, 'wsms_email_verified', '1');
        delete_user_meta($userId, 'wsms_email_placeholder');

        $pendingEmail = get_user_meta($userId, 'wsms_pending_email', true);

        if (!empty($pendingEmail) && $pendingEmail === $verifiedAddress) {
            wp_update_user([
                'ID'         => $userId,
                'user_email' => $pendingEmail,
            ]);
            delete_user_meta($userId, 'wsms_pending_email');
        }
    }

    /**
     * Look up, validate, and consume a verification token.
     *
     * @return object|array The verification record on success, or an error array on failure.
     */
    private function consumeVerification(string $token, string $type): object|array
    {
        $verification = $this->lookupVerification($token, $type);

        if ($verification === null) {
            return ['success' => false, 'error' => 'invalid_token', 'message' => 'Invalid or expired token.'];
        }

        if ($this->isVerificationExpired($verification)) {
            return ['success' => false, 'error' => 'expired_token', 'message' => 'This token has expired.'];
        }

        if ($verification->used_at !== null) {
            return ['success' => false, 'error' => 'used_token', 'message' => 'This token has already been used.'];
        }

        $this->markVerificationUsed($verification->id);

        return $verification;
    }

    /**
     * Create a verification record and send notification.
     */
    private function createVerification(int $userId, string $type, string $identifier): void
    {
        global $wpdb;

        $token = $this->otpGenerator->generateToken();
        $hashedToken = $this->otpGenerator->hash($token);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

        $wpdb->insert(
            $wpdb->prefix . 'wsms_verifications',
            [
                'user_id'    => $userId,
                'type'       => $type,
                'identifier' => $identifier,
                'code'       => $hashedToken,
                'expires_at' => $expiresAt,
            ],
        );

        $baseUrl = get_site_url();
        $authSettings = $this->getSettings();
        $authBase = $authSettings['auth_base_url'] ?? '/account';

        $siteName = get_bloginfo('name');
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if ($type === 'email_verify') {
            $link = $baseUrl . $authBase . '/verify-email?token=' . $token;
            $subject = sprintf('[%s] Verify your email address', $siteName);
            $message = sprintf(
                '<p>Please verify your email address by clicking the link below:</p>'
                . '<p><a href="%s">Verify Email</a></p>'
                . '<p>This link expires in 60 minutes.</p>'
                . '<p>If you did not create an account, please ignore this email.</p>',
                esc_url($link),
            );
        } else {
            $link = $baseUrl . $authBase . '/reset-password?token=' . $token;
            $subject = sprintf('[%s] Reset your password', $siteName);
            $message = sprintf(
                '<p>Click the link below to reset your password:</p>'
                . '<p><a href="%s">Reset Password</a></p>'
                . '<p>This link expires in 60 minutes.</p>'
                . '<p>If you did not request this, please ignore this email.</p>',
                esc_url($link),
            );
        }

        wp_mail($identifier, $subject, $message, $headers);
    }

    private function isVerificationOnCooldown(int $userId, string $type, int $cooldownSeconds = 60): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        return (bool) $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND type = %s AND used_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND) LIMIT 1",
            $userId,
            $type,
            $cooldownSeconds,
        ));
    }

    private function invalidateVerifications(int $userId, string $type): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET used_at = NOW() WHERE user_id = %d AND type = %s AND used_at IS NULL",
            $userId,
            $type,
        ));
    }

    private function lookupVerification(string $token, string $type): ?object
    {
        global $wpdb;

        $hashedToken = $this->otpGenerator->hash($token);
        $table = $wpdb->prefix . 'wsms_verifications';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s AND type = %s LIMIT 1",
            $hashedToken,
            $type,
        ));

        return $row ?: null;
    }

    private function isVerificationExpired(object $verification): bool
    {
        return strtotime($verification->expires_at) < time();
    }

    private function markVerificationUsed(int $id): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'wsms_verifications',
            ['used_at' => gmdate('Y-m-d H:i:s')],
            ['id' => $id],
        );
    }
}
