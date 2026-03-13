<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Auth\AuthSession;
use WSms\Enums\EnrollmentTiming;
use WSms\Enums\EventType;
use WSms\Enums\VerificationType;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;

defined('ABSPATH') || exit;

class AccountManager
{
    public const PLACEHOLDER_EMAIL_DOMAIN = 'noreply.wsms.local';
    public const PLACEHOLDER_USERNAME_PREFIX = 'wsms_';
    public const DEFAULT_PENDING_USER_TTL_HOURS = 24;
    private const META_HAS_USABLE_PASSWORD = 'wsms_has_usable_password';

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

    public static function isPlaceholderUsername(string $username): bool
    {
        return str_starts_with($username, self::PLACEHOLDER_USERNAME_PREFIX);
    }

    /**
     * Whether a user has a usable (known) password.
     *
     * '' = meta not set → pre-existing WP user, assume has password.
     * '1' = explicitly has password. '0' = explicitly no password (social login).
     */
    public static function hasUsablePassword(int $userId): bool
    {
        $meta = get_user_meta($userId, self::META_HAS_USABLE_PASSWORD, true);

        return $meta === '' || $meta === '1';
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

    private static function generatePlaceholderUsername(): string
    {
        return self::PLACEHOLDER_USERNAME_PREFIX . bin2hex(random_bytes(5));
    }

    private function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $raw = get_option('wsms_auth_settings', []);

        foreach (PolicyEngine::CHANNEL_DEFAULTS as $key => $defaults) {
            $raw[$key] = array_merge($defaults, $raw[$key] ?? []);
        }

        return $this->settings = $raw;
    }

    /**
     * Register a new user.
     *
     * @return array{success: bool, user_id?: int, mfa_required?: bool, error?: string, message: string}
     */
    public function registerUser(array $data, bool $socialLogin = false): array
    {
        $settings = $this->getSettings();
        $requiredFields = $settings['registration_fields'] ?? ['email', 'password'];

        // Channel-based requirement checks (default to required for backward compat).
        // Social login skips these — the social provider controls what fields are available.
        $emailRequired = $socialLogin ? false : ($settings['email']['required_at_signup'] ?? true);
        if ($emailRequired && empty($data['email'])) {
            return ['success' => false, 'error' => 'missing_email', 'message' => 'Email is required.'];
        }

        $passwordRequired = $socialLogin ? false : (!empty($settings['password']['enabled']) && ($settings['password']['required_at_signup'] ?? true));
        if ($passwordRequired && empty($data['password'])) {
            return ['success' => false, 'error' => 'missing_password', 'message' => 'Password is required.'];
        }

        $phoneRequired = $socialLogin ? false : (!empty($settings['phone']['enabled']) && !empty($settings['phone']['required_at_signup']));
        if ($phoneRequired && empty($data['phone'])) {
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
                ? self::generatePlaceholderUsername()
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

        // Check for stale pending users that can be replaced (only when verify_at_signup is active).
        $emailVerifyEnabled = !$isPlaceholder && !empty($settings['email']['enabled']) && !empty($settings['email']['verify_at_signup']);
        $phoneVerifyEnabled = !empty($settings['phone']['enabled']) && !empty($settings['phone']['verify_at_signup']);

        if ($emailVerifyEnabled || $phoneVerifyEnabled) {
            $ttlHours = (int) ($settings['pending_user_ttl_hours'] ?? self::DEFAULT_PENDING_USER_TTL_HOURS);

            if ($emailVerifyEnabled && !empty($email)) {
                $this->deleteExpiredPendingUser(get_user_by('email', $email), $ttlHours);
            }

            if ($phoneVerifyEnabled && !empty($data['phone'])) {
                $phoneUsers = get_users([
                    'meta_key'   => 'wsms_phone',
                    'meta_value' => sanitize_text_field($data['phone']),
                    'number'     => 1,
                ]);
                if (!empty($phoneUsers)) {
                    $this->deleteExpiredPendingUser($phoneUsers[0], $ttlHours);
                }
            }
        }

        // Check phone uniqueness (after pending-user cleanup so expired conflicts are cleared).
        if (!empty($data['phone']) && self::isPhoneTaken(sanitize_text_field($data['phone']))) {
            return ['success' => false, 'error' => 'phone_exists', 'message' => 'This phone number is already associated with another account.'];
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

        update_user_meta($userId, self::META_HAS_USABLE_PASSWORD, !empty($data['password']) ? '1' : '0');

        $needsVerification = $emailVerifyEnabled || (!empty($data['phone']) && $phoneVerifyEnabled);

        update_user_meta($userId, 'wsms_registration_status', $needsVerification ? 'pending' : 'active');
        if ($needsVerification) {
            update_user_meta($userId, 'wsms_registration_created_at', gmdate('Y-m-d H:i:s'));
        }

        $pendingVerifications = [];

        // Store phone meta if provided.
        if (!empty($data['phone'])) {
            $phone = sanitize_text_field($data['phone']);
            update_user_meta($userId, 'wsms_phone', $phone);

            if ($phoneVerifyEnabled) {
                $this->createChannelVerification($userId, 'phone', $phone);
                $pendingVerifications[] = ['type' => 'phone', 'status' => 'pending'];
            }
        }

        // Generate and send email verification only when required (skip for placeholder emails).
        if (!empty($email) && $emailVerifyEnabled) {
            $this->createChannelVerification($userId, 'email', $email);
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

        $this->createVerification($user->ID, VerificationType::PasswordReset->value, $email);

        $this->auditLogger->log(EventType::PasswordResetRequest, 'success', $user->ID);
    }

    /**
     * Complete a password reset.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function completePasswordReset(string $token, string $newPassword): array
    {
        $verification = $this->consumeVerification($token, VerificationType::PasswordReset->value);

        if (is_array($verification)) {
            return $verification;
        }

        $userId = (int) $verification->user_id;

        wp_set_password($newPassword, $userId);
        update_user_meta($userId, self::META_HAS_USABLE_PASSWORD, '1');

        $this->auditLogger->log(EventType::PasswordResetComplete, 'success', $userId);

        return ['success' => true, 'message' => 'Password has been reset successfully.'];
    }

    /**
     * Verify an email address using a token.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function verifyEmail(string $token): array
    {
        $verification = $this->consumeVerification($token, VerificationType::EmailVerify->value);

        if (is_array($verification)) {
            return $verification;
        }

        $userId = (int) $verification->user_id;
        $this->markEmailVerified($userId, $verification->identifier);
        $this->auditLogger->log(EventType::EmailVerified, 'success', $userId);
        $this->maybeActivateUser($userId);

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

        // Determine which channels have actual changes.
        $phoneChanged = false;
        $emailChanged = false;
        $phone = null;

        if (isset($data['phone'])) {
            $phone = sanitize_text_field($data['phone']);
            $currentPhone = get_user_meta($userId, 'wsms_phone', true);
            $phoneChanged = ($phone !== $currentPhone);
        }

        if (isset($newEmail)) {
            $currentEmail = get_userdata($userId)?->user_email ?? '';
            $emailChanged = ($newEmail !== $currentEmail);
        }

        // Check all cooldowns before any writes.
        $settings = $this->getSettings();

        if ($phoneChanged) {
            $cooldown = (int) ($settings['phone']['cooldown'] ?? 60);
            if ($this->isVerificationOnCooldown($userId, VerificationType::PhoneVerify->value, $cooldown)) {
                return ['success' => false, 'error' => 'cooldown', 'message' => 'Please wait before changing your phone number.'];
            }

            if (self::isPhoneTaken($phone, $userId)) {
                return ['success' => false, 'error' => 'phone_exists', 'message' => 'This phone number is already associated with another account.'];
            }
        }

        if ($emailChanged) {
            $cooldown = (int) ($settings['email']['cooldown'] ?? 60);
            if ($this->isVerificationOnCooldown($userId, VerificationType::EmailVerify->value, $cooldown)) {
                return ['success' => false, 'error' => 'cooldown', 'message' => 'Please wait before changing your email.'];
            }
        }

        // All validations passed — apply writes.
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

        if ($phoneChanged) {
            update_user_meta($userId, 'wsms_pending_phone', $phone);
            $this->invalidateVerifications($userId, VerificationType::PhoneVerify->value);
            $this->createChannelVerification($userId, 'phone', $phone);
            $result['phone_verification_required'] = true;
        }

        if ($emailChanged) {
            $this->invalidateVerifications($userId, VerificationType::EmailVerify->value);
            update_user_meta($userId, 'wsms_pending_email', $newEmail);
            $this->createChannelVerification($userId, 'email', $newEmail);
            $result['email_verification_required'] = true;
        }

        return $result;
    }

    /**
     * Change user password.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function changePassword(int $userId, ?string $currentPassword, string $newPassword): array
    {
        $user = get_userdata($userId);

        if (!$user) {
            return ['success' => false, 'error' => 'user_not_found', 'message' => 'User not found.'];
        }

        $hasUsablePassword = self::hasUsablePassword($userId);

        if ($hasUsablePassword) {
            if (empty($currentPassword) || !wp_check_password($currentPassword, $user->user_pass, $userId)) {
                return ['success' => false, 'error' => 'wrong_password', 'message' => 'Current password is incorrect.'];
            }
        }

        wp_set_password($newPassword, $userId);
        update_user_meta($userId, self::META_HAS_USABLE_PASSWORD, '1');
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
     * Verify a channel using an OTP code.
     *
     * Verify any channel that stores OTP verifications with type '{channel}_verify'.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function verifyChannelOtp(int $userId, string $channel, string $code): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';
        $verifyType = VerificationType::forChannel($channel)->value;

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND type = %s AND used_at IS NULL ORDER BY created_at DESC LIMIT 1",
            $userId,
            $verifyType,
        ));

        if (!$verification) {
            return ['success' => false, 'error' => 'no_verification', 'message' => "No pending {$channel} verification."];
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

        // Apply channel-specific post-verification actions.
        $this->applyChannelVerified($userId, $channel, $verification->identifier);
        $this->maybeActivateUser($userId);

        $channelLabel = ucfirst($channel);

        return ['success' => true, 'message' => "{$channelLabel} verified successfully."];
    }

    /**
     * Resend a verification code/link for any channel.
     *
     * Resend a verification code/link for the given channel.
     *
     * @return array{success: bool, error?: string, message: string}
     */
    public function resendVerification(int $userId, string $channel): array
    {
        $identifier = $this->getChannelIdentifier($userId, $channel);

        if ($identifier === null) {
            return ['success' => false, 'error' => "no_{$channel}", 'message' => "No {$channel} on file."];
        }

        $settings = $this->getSettings();
        $verifyType = VerificationType::forChannel($channel)->value;
        $cooldown = (int) ($settings[$channel]['cooldown'] ?? 60);

        if ($this->isVerificationOnCooldown($userId, $verifyType, $cooldown)) {
            return ['success' => false, 'error' => 'cooldown', 'message' => 'Please wait before requesting a new code.'];
        }

        $this->invalidateVerifications($userId, $verifyType);
        $this->createChannelVerification($userId, $channel, $identifier);

        return ['success' => true, 'message' => 'Verification resent.'];
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

        foreach ($state as $channel => $channelState) {
            if ($channelState['has'] && !$channelState['verified']) {
                $pending[] = ['type' => $channel, 'status' => 'pending'];
            }
        }

        return [
            'pending_verifications' => $pending,
            'all_verified'          => empty($pending),
        ];
    }

    /**
     * Send a fresh verification challenge for login-time enforcement.
     * Uses the confirmed identifier (not pending) to prevent sending codes to unverified addresses.
     */
    public function sendVerificationChallenge(int $userId, string $channel): void
    {
        $identifier = $this->getConfirmedIdentifier($userId, $channel);

        if ($identifier === null) {
            return;
        }

        $verifyType = VerificationType::forChannel($channel)->value;
        $this->invalidateVerifications($userId, $verifyType);
        $this->createChannelVerification($userId, $channel, $identifier);
    }

    /**
     * Get the user's identifier for a given channel, preferring pending values.
     * Used by resend/profile verification flows where the pending address is the target.
     */
    private function getChannelIdentifier(int $userId, string $channel): ?string
    {
        if ($channel === 'phone') {
            $pending = get_user_meta($userId, 'wsms_pending_phone', true);
            if (!empty($pending)) {
                return $pending;
            }
        }

        if ($channel === 'email') {
            $pending = get_user_meta($userId, 'wsms_pending_email', true);
            if (!empty($pending)) {
                return $pending;
            }
        }

        return $this->getConfirmedIdentifier($userId, $channel);
    }

    /**
     * Get the user's confirmed (canonical) identifier for a given channel.
     */
    private function getConfirmedIdentifier(int $userId, string $channel): ?string
    {
        if ($channel === 'phone') {
            $phone = get_user_meta($userId, 'wsms_phone', true);
            return !empty($phone) ? $phone : null;
        }

        if ($channel === 'email') {
            $email = get_userdata($userId)?->user_email;
            return (!empty($email) && !self::isPlaceholderEmail($email)) ? $email : null;
        }

        // For future channels, check user meta by convention: wsms_{channel}.
        $value = get_user_meta($userId, 'wsms_' . $channel, true);
        return !empty($value) ? $value : null;
    }

    /**
     * Create a verification record for any channel and deliver it.
     */
    private function createChannelVerification(int $userId, string $channel, string $identifier): void
    {
        if ($channel === 'email' && !$this->emailUsesOtp()) {
            $this->createVerification($userId, VerificationType::EmailVerify->value, $identifier);

            return;
        }

        $otp = $this->createOtpVerification($userId, $channel, $identifier);

        // Channel-specific delivery.
        if ($channel === 'phone') {
            do_action('wsms_send_sms', $identifier, sprintf('Your verification code is: %s', $otp));
        } elseif ($channel === 'email') {
            $this->sendVerificationEmail($identifier, $otp, $channel);
        } else {
            do_action('wsms_channel_verification_created', $userId, $channel, $identifier, $otp);
        }
    }

    /**
     * Apply channel-specific actions after successful OTP verification.
     */
    private function applyChannelVerified(int $userId, string $channel, string $identifier): void
    {
        if ($channel === 'phone') {
            $this->markPhoneVerified($userId, $identifier);
            $this->auditLogger->log(EventType::PhoneVerified, 'success', $userId);
            return;
        }

        if ($channel === 'email') {
            $this->markEmailVerified($userId, $identifier);
            $this->auditLogger->log(EventType::EmailVerified, 'success', $userId);
            return;
        }

        // For future channels: mark as verified via convention.
        update_user_meta($userId, 'wsms_' . $channel . '_verified', '1');
        $this->auditLogger->log(EventType::OtpVerified, 'success', $userId, [
            'channel' => $channel,
        ]);
    }

    /**
     * Create an OTP verification record for any channel.
     *
     * @return string The plaintext OTP (caller is responsible for delivery).
     */
    private function createOtpVerification(int $userId, string $channel, string $identifier): string
    {
        global $wpdb;

        $settings = $this->getSettings();
        $channelSettings = $settings[$channel] ?? [];
        $codeLength = (int) ($channelSettings['code_length'] ?? 6);
        $expiry = (int) ($channelSettings['expiry'] ?? 300);
        $maxAttempts = (int) ($channelSettings['max_attempts'] ?? 5);

        $otp = $this->otpGenerator->generate($codeLength);
        $verifyType = VerificationType::forChannel($channel)->value;
        do_action('wsms_otp_generated', $userId, $otp, $verifyType);
        $hashed = $this->otpGenerator->hash($otp);

        $wpdb->insert($wpdb->prefix . 'wsms_verifications', [
            'user_id'      => $userId,
            'type'         => $verifyType,
            'identifier'   => $identifier,
            'code'         => $hashed,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        return $otp;
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
     * Send a verification OTP via email.
     */
    private function sendVerificationEmail(string $email, string $otp, string $channel): void
    {
        $settings = $this->getSettings();
        $expiry = (int) (($settings[$channel] ?? [])['expiry'] ?? 300);

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
     * Transition a pending user to active if all required verifications are complete
     * (or if the admin has since disabled verify_at_signup).
     */
    public function maybeActivateUser(int $userId): void
    {
        $status = get_user_meta($userId, 'wsms_registration_status', true);
        if ($status !== 'pending') {
            return;
        }

        $settings = $this->getSettings();
        $state = self::getUserVerificationState($userId);

        // Collect all channels that require verify_at_signup.
        $requiredChannels = [];
        foreach ($state as $channel => $channelState) {
            if (!empty($settings[$channel]['enabled']) && !empty($settings[$channel]['verify_at_signup'])) {
                $requiredChannels[] = $channel;
            }
        }

        // If admin disabled all verify_at_signup, auto-activate.
        if (empty($requiredChannels)) {
            $this->activateUser($userId);
            return;
        }

        // Check each still-required verification.
        foreach ($requiredChannels as $channel) {
            if (($state[$channel]['has'] ?? false) && !($state[$channel]['verified'] ?? true)) {
                return;
            }
        }

        $this->activateUser($userId);
    }

    private function activateUser(int $userId): void
    {
        update_user_meta($userId, 'wsms_registration_status', 'active');
        delete_user_meta($userId, 'wsms_registration_created_at');
    }

    private function deleteExpiredPendingUser($user, int $ttlHours): void
    {
        if (!$user) {
            return;
        }

        $status = get_user_meta($user->ID, 'wsms_registration_status', true);
        $createdAt = get_user_meta($user->ID, 'wsms_registration_created_at', true);

        if ($status === 'pending' && !empty($createdAt)) {
            if (time() > strtotime($createdAt) + ($ttlHours * 3600)) {
                if (!function_exists('wp_delete_user')) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }
                wp_delete_user($user->ID);
            }
        }
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
     * Mark phone as verified and apply any pending phone change.
     */
    private function markPhoneVerified(int $userId, string $verifiedPhone): void
    {
        update_user_meta($userId, 'wsms_phone_verified', '1');

        $pendingPhone = get_user_meta($userId, 'wsms_pending_phone', true);

        if (!empty($pendingPhone) && $pendingPhone === $verifiedPhone) {
            update_user_meta($userId, 'wsms_phone', $pendingPhone);
            delete_user_meta($userId, 'wsms_pending_phone');

            // Sync MFA phone factor if enrolled.
            $this->mfaManager->updateFactorMeta($userId, 'phone', ['phone' => $pendingPhone]);
        }
    }

    /**
     * Cancel a pending phone or email change.
     */
    public function cancelPendingChange(int $userId, string $channel): void
    {
        $metaKey = 'wsms_pending_' . $channel;
        delete_user_meta($userId, $metaKey);
        $this->invalidateVerifications($userId, VerificationType::forChannel($channel)->value);
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

        if ($type === VerificationType::EmailVerify->value) {
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

        $cutoff = gmdate('Y-m-d H:i:s', time() - $cooldownSeconds);

        return (bool) $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND type = %s AND used_at IS NULL AND created_at > %s LIMIT 1",
            $userId,
            $type,
            $cutoff,
        ));
    }

    private function invalidateVerifications(int $userId, string $type): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET used_at = %s WHERE user_id = %d AND type = %s AND used_at IS NULL",
            gmdate('Y-m-d H:i:s'),
            $userId,
            $type,
        ));
    }

    /**
     * Check if a phone number is already in use by another user.
     */
    public static function isPhoneTaken(string $phone, ?int $excludeUserId = null): bool
    {
        $args = [
            'meta_key'   => 'wsms_phone',
            'meta_value' => $phone,
            'number'     => 1,
            'fields'     => 'ID',
        ];

        if ($excludeUserId !== null) {
            $args['exclude'] = [$excludeUserId];
        }

        return !empty(get_users($args));
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
