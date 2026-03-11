<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Enums\EnrollmentTiming;
use WSms\Enums\EventType;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;

defined('ABSPATH') || exit;

class AccountManager
{
    public function __construct(
        private AuditLogger $auditLogger,
        private OtpGenerator $otpGenerator,
        private MfaManager $mfaManager,
    ) {
    }

    /**
     * Register a new user.
     *
     * @return array{success: bool, user_id?: int, mfa_required?: bool, error?: string, message: string}
     */
    public function registerUser(array $data): array
    {
        $settings = get_option('wsms_auth_settings', []);
        $requiredFields = $settings['registration_fields'] ?? ['email', 'password'];

        // Validate required fields.
        if (in_array('email', $requiredFields, true) && empty($data['email'])) {
            return ['success' => false, 'error' => 'missing_email', 'message' => 'Email is required.'];
        }

        if (in_array('password', $requiredFields, true) && empty($data['password'])) {
            return ['success' => false, 'error' => 'missing_password', 'message' => 'Password is required.'];
        }

        $email = sanitize_email($data['email'] ?? '');

        if (!empty($email) && !is_email($email)) {
            return ['success' => false, 'error' => 'invalid_email', 'message' => 'Invalid email address.'];
        }

        $username = !empty($data['username']) ? sanitize_user($data['username']) : $email;

        $userdata = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $data['password'] ?? bin2hex(random_bytes(16)),
        ];

        if (!empty($data['display_name'])) {
            $userdata['display_name'] = sanitize_text_field($data['display_name']);
        }

        $userId = wp_insert_user($userdata);

        if (is_wp_error($userId)) {
            return [
                'success' => false,
                'error'   => $userId->get_error_code(),
                'message' => $userId->get_error_message(),
            ];
        }

        // Store phone meta if provided.
        if (!empty($data['phone'])) {
            update_user_meta($userId, 'wsms_phone', sanitize_text_field($data['phone']));
        }

        // Generate and send email verification.
        if (!empty($email)) {
            $this->createVerification($userId, 'email_verify', $email);
        }

        $this->auditLogger->log(EventType::Register, 'success', $userId, [
            'method' => 'registration',
        ]);

        $result = [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Registration successful.',
        ];

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
        update_user_meta($userId, 'wsms_email_verified', '1');

        // Check for pending email change.
        $pendingEmail = get_user_meta($userId, 'wsms_pending_email', true);

        if (!empty($pendingEmail) && $pendingEmail === $verification->identifier) {
            wp_update_user([
                'ID'         => $userId,
                'user_email' => $pendingEmail,
            ]);
            delete_user_meta($userId, 'wsms_pending_email');
        }

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

        if (isset($data['display_name'])) {
            wp_update_user([
                'ID'           => $userId,
                'display_name' => sanitize_text_field($data['display_name']),
            ]);
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

        if ($type === 'email_verify') {
            $link = $baseUrl . '/account/verify-email?token=' . $token;
            $subject = 'Verify your email address';
            $message = "Please verify your email by clicking this link:\n\n{$link}";
        } else {
            $link = $baseUrl . '/account/reset-password?token=' . $token;
            $subject = 'Reset your password';
            $message = "Click this link to reset your password:\n\n{$link}";
        }

        wp_mail($identifier, $subject, $message);
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
