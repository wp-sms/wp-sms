<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Auth\AuthSession;
use WSms\Dependencies\Psr\Log\LoggerInterface;
use WSms\Enums\EnrollmentTiming;
use WSms\Enums\EventType;
use WSms\Enums\SessionStage;
use WSms\Enums\TemplateType;
use WSms\Enums\VerificationType;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\TemplateManager;
use WSms\Mfa\MfaManager;
use WSms\Auth\ValueObjects\OperationResult;
use WSms\Enums\AuthErrorCode;
use WSms\PhoneRestriction\SendingPolicyGuard;
use WSms\Support\PhoneValidator;
use WSms\Support\UserMeta;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class AccountManager
{
    private const USERNAME_FALLBACK = 'user';
    /** WordPress `user_login` column is 60 chars; reserve 10 for numeric suffix. */
    private const USERNAME_MAX_BASE_LENGTH = 50;

    /** @var (\Closure(): SendingPolicyGuard)|null */
    private ?\Closure $sendingPolicyGuardResolver = null;

    public function __construct(
        private AuditLogger $auditLogger,
        private OtpService $otpService,
        private MfaManager $mfaManager,
        private AuthSession $authSession,
        private SettingsRepository $settingsRepo,
        private MessageDispatcher $messageDispatcher,
        private TemplateManager $templateManager,
        private ?ProfileFieldRegistry $fieldRegistry = null,
        private ?TrustedDeviceManager $trustedDevices = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /** @param \Closure(): SendingPolicyGuard $resolver */
    public function setSendingPolicyGuardResolver(\Closure $resolver): void
    {
        $this->sendingPolicyGuardResolver = $resolver;
    }

    private static function generatePlaceholderEmail(): string
    {
        return bin2hex(random_bytes(5)) . '@' . UserInfo::PLACEHOLDER_EMAIL_DOMAIN;
    }

    private static function generatePlaceholderUsername(): string
    {
        return UserInfo::PLACEHOLDER_USERNAME_PREFIX . bin2hex(random_bytes(5));
    }

    /**
     * Generate a unique username from an email address.
     */
    private static function generateUsernameFromEmail(string $email): string
    {
        $localPart = strstr($email, '@', true);

        if ($localPart === false || $localPart === '') {
            $localPart = self::USERNAME_FALLBACK;
        }

        $username = sanitize_user($localPart, true);

        if ($username === '') {
            $username = self::USERNAME_FALLBACK;
        }

        $username = mb_substr($username, 0, self::USERNAME_MAX_BASE_LENGTH);

        if (!username_exists($username)) {
            return $username;
        }

        for ($suffix = 2; $suffix <= 9999; $suffix++) {
            $candidate = $username . $suffix;
            if (!username_exists($candidate)) {
                return $candidate;
            }
        }

        // Exhausted numeric suffixes — fall back to random
        return UserInfo::PLACEHOLDER_USERNAME_PREFIX . bin2hex(random_bytes(5));
    }

    /**
     * Register a new user.
     */
    public function registerUser(array $data, bool $socialLogin = false, ?RegistrationForm $form = null): OperationResult
    {
        $settings = $this->settingsRepo->all();

        if (empty($settings['enable_registration'])) {
            return OperationResult::fail(
                AuthErrorCode::RegistrationDisabled,
                __('Registration is not available.', 'wp-sms'),
            );
        }

        if ($form) {
            $settings = RegistrationForm::applyOverrides($settings, $form->getAuthOverrides());
        }

        $emailRequired = $socialLogin ? false : (!empty($settings['email']['enabled']) && !empty($settings['email']['required_at_signup']));

        $validationError = $this->validateRegistrationFields($data, $settings, $socialLogin, $emailRequired, $form);
        if ($validationError !== null) {
            return $validationError;
        }

        [$email, $isPlaceholder, $username, $userdata] = $this->resolveEmailAndUsername($data, $emailRequired);

        if ($form && $form->getUserRole() !== '') {
            $role = $form->getUserRole();
            // Validate role still exists; fall back to WP default if not.
            if (wp_roles()->is_role($role)) {
                $userdata['role'] = $role;
            }
        }

        $emailVerifyEnabled = !$isPlaceholder && !empty($settings['email']['enabled']) && !empty($settings['email']['verify_at_signup']);
        $phoneVerifyEnabled = !empty($settings['phone']['enabled']) && !empty($settings['phone']['verify_at_signup']);

        // Only verify channels included in the form's field list.
        if ($form && $emailVerifyEnabled) {
            $emailVerifyEnabled = $form->hasField('email');
        }
        if ($form && $phoneVerifyEnabled) {
            $phoneVerifyEnabled = $form->hasField('phone');
        }

        $this->cleanupExpiredPendingUsers($data, $email, $emailVerifyEnabled, $phoneVerifyEnabled, $settings);

        if (!empty($data['phone']) && UserInfo::isPhoneTaken($data['phone'])) {
            return OperationResult::fail(AuthErrorCode::PhoneExists, __('This phone number is already associated with another account.', 'wp-sms'));
        }

        $phoneRestriction = $this->checkPhoneRestriction($data['phone'] ?? '');

        if ($phoneRestriction !== null) {
            return $phoneRestriction;
        }

        $userId = wp_insert_user($userdata);

        if (is_wp_error($userId)) {
            return OperationResult::fail((string) $userId->get_error_code(), $userId->get_error_message());
        }

        $this->storeUserMeta($userId, $data, $isPlaceholder, $emailVerifyEnabled, $phoneVerifyEnabled);
        $this->writeCustomFields($userId, $data);

        if ($form) {
            update_user_meta($userId, 'wsms_registration_form_id', $form->getId());
            do_action('wsms_form_registration', $userId, $form);
        }

        $pendingVerifications = $this->setupVerifications($userId, $data, $email, $emailVerifyEnabled, $phoneVerifyEnabled);

        $this->auditLogger->log(EventType::Register, 'success', $userId, [
            'method' => 'registration',
        ]);

        return $this->buildRegistrationResult($userId, $pendingVerifications, $settings, $form);
    }

    /**
     * @return OperationResult|null Error result if validation fails, null if all checks pass.
     */
    private function validateRegistrationFields(array $data, array $settings, bool $socialLogin, bool $emailRequired, ?RegistrationForm $form = null): ?OperationResult
    {
        $requiredFields = $settings['registration_fields'] ?? ['email', 'password'];

        if ($form) {
            $emailRequired = $emailRequired && $form->hasField('email') && $form->isFieldRequired('email');
        }

        if ($emailRequired && empty($data['email'])) {
            return OperationResult::fail(AuthErrorCode::MissingEmail, __('Email is required.', 'wp-sms'));
        }

        $passwordRequired = $socialLogin ? false : (!empty($settings['password']['enabled']) && !empty($settings['password']['required_at_signup']));
        if ($form) {
            $passwordRequired = $passwordRequired && $form->hasField('password') && $form->isFieldRequired('password');
        }
        if ($passwordRequired && empty($data['password'])) {
            return OperationResult::fail(AuthErrorCode::MissingPassword, __('Password is required.', 'wp-sms'));
        }

        $phoneRequired = $socialLogin ? false : (!empty($settings['phone']['enabled']) && !empty($settings['phone']['required_at_signup']));
        if ($form) {
            $phoneRequired = $phoneRequired && $form->hasField('phone') && $form->isFieldRequired('phone');
        }
        if ($phoneRequired && empty($data['phone'])) {
            return OperationResult::fail(AuthErrorCode::MissingPhone, __('Phone number is required.', 'wp-sms'));
        }

        $checkFirstName = $form
            ? $form->hasField('first_name') && $form->isFieldRequired('first_name')
            : in_array('first_name', $requiredFields, true);
        if ($checkFirstName && empty($data['first_name'])) {
            return OperationResult::fail(AuthErrorCode::MissingFirstName, __('First name is required.', 'wp-sms'));
        }

        $checkLastName = $form
            ? $form->hasField('last_name') && $form->isFieldRequired('last_name')
            : in_array('last_name', $requiredFields, true);
        if ($checkLastName && empty($data['last_name'])) {
            return OperationResult::fail(AuthErrorCode::MissingLastName, __('Last name is required.', 'wp-sms'));
        }

        // Validate email format when provided.
        if (!empty($data['email'])) {
            $sanitized = sanitize_email($data['email']);
            if (!empty($sanitized) && !is_email($sanitized)) {
                return OperationResult::fail(AuthErrorCode::InvalidEmail, __('Invalid email address.', 'wp-sms'));
            }
        }

        // Validate required custom fields.
        if (!$socialLogin && $this->fieldRegistry) {
            foreach ($this->fieldRegistry->getFieldsForContext('registration') as $field) {
                if ($field->isSystem()) {
                    continue;
                }

                if ($form) {
                    if (!$form->hasField($field->id)) {
                        continue;
                    }
                    $isRequired = $form->isFieldRequired($field->id);
                } else {
                    $isRequired = $field->required;
                }

                if ($isRequired && empty($data[$field->id])) {
                    return OperationResult::fail('missing_' . $field->id, sprintf(__('%s is required.', 'wp-sms'), $field->label));
                }
            }
        }

        return null;
    }

    /**
     * Resolve email, username, and WP userdata from registration input.
     *
     * @return array{0: string, 1: bool, 2: string, 3: array} [email, isPlaceholder, username, userdata]
     */
    private function resolveEmailAndUsername(array $data, bool $emailRequired): array
    {
        $email = sanitize_email($data['email'] ?? '');
        $isPlaceholder = false;

        if (empty($email) && !$emailRequired) {
            $email = self::generatePlaceholderEmail();
            $isPlaceholder = true;
        }

        $username = !empty($data['username'])
            ? sanitize_user($data['username'])
            : ($isPlaceholder
                ? self::generatePlaceholderUsername()
                : self::generateUsernameFromEmail($email));

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

        return [$email, $isPlaceholder, $username, $userdata];
    }

    private function cleanupExpiredPendingUsers(array $data, string $email, bool $emailVerifyEnabled, bool $phoneVerifyEnabled, array $settings): void
    {
        if (!$emailVerifyEnabled && !$phoneVerifyEnabled) {
            return;
        }

        $ttlHours = (int) ($settings['pending_user_ttl_hours'] ?? UserInfo::DEFAULT_PENDING_USER_TTL_HOURS);

        if ($emailVerifyEnabled && !empty($email)) {
            $this->deleteExpiredPendingUser(get_user_by('email', $email), $ttlHours);
        }

        if ($phoneVerifyEnabled && !empty($data['phone'])) {
            $phoneUsers = get_users([
                'meta_key'   => UserMeta::PHONE,
                'meta_value' => sanitize_text_field($data['phone']),
                'number'     => 1,
            ]);
            if (!empty($phoneUsers)) {
                $this->deleteExpiredPendingUser($phoneUsers[0], $ttlHours);
            }
        }
    }

    private function storeUserMeta(int $userId, array $data, bool $isPlaceholder, bool $emailVerifyEnabled, bool $phoneVerifyEnabled): void
    {
        if ($isPlaceholder) {
            update_user_meta($userId, UserMeta::EMAIL_PLACEHOLDER, '1');
        }

        update_user_meta($userId, UserMeta::HAS_USABLE_PASSWORD, !empty($data['password']) ? '1' : '0');

        $needsVerification = $emailVerifyEnabled || (!empty($data['phone']) && $phoneVerifyEnabled);

        update_user_meta($userId, UserMeta::REGISTRATION_STATUS, $needsVerification ? 'pending' : 'active');
        if ($needsVerification) {
            update_user_meta($userId, UserMeta::REGISTRATION_CREATED_AT, gmdate('Y-m-d H:i:s'));
        }

        if (!empty($data['phone'])) {
            update_user_meta($userId, UserMeta::PHONE, PhoneValidator::assertE164($data['phone']));
        }
    }

    /**
     * Write custom profile field values for a user.
     * Uses default_value when the user didn't submit a value and a default exists.
     */
    private function writeCustomFields(int $userId, array $data): void
    {
        if (!$this->fieldRegistry) {
            return;
        }

        foreach ($this->fieldRegistry->getCustomFields() as $field) {
            if (array_key_exists($field->id, $data)) {
                $this->fieldRegistry->writeValue($userId, $field, $data[$field->id]);
            } elseif ($field->defaultValue !== '') {
                $this->fieldRegistry->writeValue($userId, $field, $field->defaultValue);
            }
        }
    }

    /**
     * Read custom profile field values for a user.
     *
     * @return array<string, mixed>
     */
    public function readCustomFields(int $userId): array
    {
        if (!$this->fieldRegistry) {
            return [];
        }

        $values = [];
        foreach ($this->fieldRegistry->getCustomFields() as $field) {
            $values[$field->id] = $this->fieldRegistry->readValue($userId, $field);
        }

        return $values;
    }

    /**
     * @return array<int, array{type: string, status: string}>
     */
    private function setupVerifications(int $userId, array $data, string $email, bool $emailVerifyEnabled, bool $phoneVerifyEnabled): array
    {
        $pendingVerifications = [];

        if (!empty($data['phone']) && $phoneVerifyEnabled) {
            $phone = sanitize_text_field($data['phone']);

            if ($this->createChannelVerification($userId, 'phone', $phone)) {
                $pendingVerifications[] = ['type' => 'phone', 'status' => 'pending'];
            }
        }

        if (!empty($email) && $emailVerifyEnabled) {
            $this->createChannelVerification($userId, 'email', $email);
            $pendingVerifications[] = ['type' => 'email', 'status' => 'pending'];
        }

        return $pendingVerifications;
    }

    private function buildRegistrationResult(int $userId, array $pendingVerifications, array $settings, ?RegistrationForm $form = null): OperationResult
    {
        $meta = [];

        if (!empty($pendingVerifications)) {
            $meta['pending_verifications'] = $pendingVerifications;
            $meta['session_token'] = $this->authSession->create(
                $userId,
                'registration',
                SessionStage::RegistrationVerify,
            );
        } else {
            $this->sendWelcomeMessage($userId);
        }

        $timing = EnrollmentTiming::tryFrom($settings['enrollment_timing']);

        if ($timing === EnrollmentTiming::OnRegistration) {
            $meta['mfa_required'] = true;
        }

        if ($form && $form->getRedirectUrl() !== '') {
            $meta['redirect_url'] = $form->getRedirectUrl();
        }

        return new OperationResult(
            success: true,
            message: __('Registration successful.', 'wp-sms'),
            userId: $userId,
            meta: $meta,
        );
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
     */
    public function completePasswordReset(string $token, string $newPassword): OperationResult
    {
        $verification = $this->consumeVerification($token, VerificationType::PasswordReset->value);

        if ($verification instanceof OperationResult) {
            return $verification;
        }

        $userId = (int) $verification->user_id;

        wp_set_password($newPassword, $userId);
        update_user_meta($userId, UserMeta::HAS_USABLE_PASSWORD, '1');
        $this->trustedDevices?->revokeAll($userId);

        $this->auditLogger->log(EventType::PasswordResetComplete, 'success', $userId);

        return OperationResult::ok(__('Password has been reset successfully.', 'wp-sms'));
    }

    /**
     * Verify an email address using a token.
     */
    public function verifyEmail(string $token): OperationResult
    {
        $verification = $this->consumeVerification($token, VerificationType::EmailVerify->value);

        if ($verification instanceof OperationResult) {
            return $verification;
        }

        $userId = (int) $verification->user_id;
        $this->markEmailVerified($userId, $verification->identifier);
        $this->auditLogger->log(EventType::EmailVerified, 'success', $userId);
        $this->maybeActivateUser($userId);

        return OperationResult::ok(__('Email verified successfully.', 'wp-sms'));
    }

    /**
     * Update user profile.
     */
    public function updateProfile(int $userId, array $data): OperationResult
    {
        // Validate all inputs before writing anything.
        if (isset($data['email'])) {
            $newEmail = sanitize_email($data['email']);

            if (!is_email($newEmail)) {
                return OperationResult::fail(AuthErrorCode::InvalidEmail, __('Invalid email address.', 'wp-sms'));
            }
        }

        // Determine which channels have actual changes.
        $phoneChanged = false;
        $emailChanged = false;
        $phone = null;

        if (isset($data['phone'])) {
            $phone = $data['phone'] === '' ? '' : PhoneValidator::assertE164($data['phone']);
            $currentPhone = get_user_meta($userId, UserMeta::PHONE, true);
            $phoneChanged = ($phone !== $currentPhone);
        }

        if (isset($newEmail)) {
            $currentEmail = get_userdata($userId)?->user_email ?? '';
            $emailChanged = ($newEmail !== $currentEmail);
        }

        // Check all cooldowns before any writes.
        $settings = $this->settingsRepo->all();

        if ($phoneChanged) {
            $cooldown = (int) $settings['phone']['cooldown'];
            if ($this->isVerificationOnCooldown($userId, VerificationType::PhoneVerify->value, $cooldown)) {
                return OperationResult::fail(AuthErrorCode::Cooldown, __('Please wait before changing your phone number.', 'wp-sms'));
            }

            if (UserInfo::isPhoneTaken($phone, $userId)) {
                return OperationResult::fail(AuthErrorCode::PhoneExists, __('This phone number is already associated with another account.', 'wp-sms'));
            }

            $phoneRestriction = $this->checkPhoneRestriction($phone);

            if ($phoneRestriction !== null) {
                return $phoneRestriction;
            }
        }

        if ($emailChanged) {
            $cooldown = (int) $settings['email']['cooldown'];
            if ($this->isVerificationOnCooldown($userId, VerificationType::EmailVerify->value, $cooldown)) {
                return OperationResult::fail(AuthErrorCode::Cooldown, __('Please wait before changing your email.', 'wp-sms'));
            }
        }

        // All validations passed — apply writes.
        $meta = [];

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
            if ($phone === '') {
                delete_user_meta($userId, UserMeta::PHONE);
                delete_user_meta($userId, UserMeta::PENDING_PHONE);
                delete_user_meta($userId, UserMeta::PHONE_VERIFIED);
            } else {
                $deliveryChannel = $settings['phone']['delivery_channel'];
                $canDeliver = $this->messageDispatcher->canDeliverToChannel(
                    $deliveryChannel,
                    $this->getOtpGatewayId('phone')
                );

                if ($canDeliver) {
                    $this->invalidateVerifications($userId, VerificationType::PhoneVerify->value);
                    $sent = $this->createChannelVerification($userId, 'phone', $phone);

                    if ($sent) {
                        update_user_meta($userId, UserMeta::PENDING_PHONE, $phone);
                        $meta['phone_verification_required'] = true;
                    } else {
                        $this->savePhoneDirectly($userId, $phone);
                    }
                } else {
                    $this->savePhoneDirectly($userId, $phone);
                }
            }
        }

        if ($emailChanged) {
            $this->invalidateVerifications($userId, VerificationType::EmailVerify->value);

            if ($this->emailUsesOtp()) {
                $canDeliverEmail = $this->messageDispatcher->canDeliverToChannel(
                    'email',
                    $this->getOtpGatewayId('email')
                );

                if ($canDeliverEmail) {
                    $sent = $this->createChannelVerification($userId, 'email', $newEmail);

                    if ($sent) {
                        update_user_meta($userId, UserMeta::PENDING_EMAIL, $newEmail);
                        $meta['email_verification_required'] = true;
                    } else {
                        $this->saveEmailDirectly($userId, $newEmail);
                    }
                } else {
                    $this->saveEmailDirectly($userId, $newEmail);
                }
            } else {
                // wp_mail is always available — no gateway check needed.
                update_user_meta($userId, UserMeta::PENDING_EMAIL, $newEmail);
                $this->createChannelVerification($userId, 'email', $newEmail);
                $meta['email_verification_required'] = true;
            }
        }

        // Write custom profile fields.
        $this->writeCustomFields($userId, $data);

        return OperationResult::ok(__('Profile updated.', 'wp-sms'), $meta);
    }

    /**
     * Change user password.
     */
    public function changePassword(int $userId, ?string $currentPassword, string $newPassword): OperationResult
    {
        $user = get_userdata($userId);

        if (!$user) {
            return OperationResult::fail(AuthErrorCode::UserNotFound, __('User not found.', 'wp-sms'));
        }

        $hasUsablePassword = UserInfo::hasUsablePassword($userId);

        if ($hasUsablePassword) {
            if (empty($currentPassword) || !wp_check_password($currentPassword, $user->user_pass, $userId)) {
                return OperationResult::fail(AuthErrorCode::WrongPassword, __('Current password is incorrect.', 'wp-sms'));
            }
        }

        wp_set_password($newPassword, $userId);
        update_user_meta($userId, UserMeta::HAS_USABLE_PASSWORD, '1');
        $this->trustedDevices?->revokeAll($userId);
        wp_set_auth_cookie($userId, false);
        wp_set_current_user($userId);

        $this->auditLogger->log(EventType::PasswordChange, 'success', $userId);

        return OperationResult::ok(__('Password changed successfully.', 'wp-sms'));
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
     */
    public function verifyChannelOtp(int $userId, string $channel, string $code): OperationResult
    {
        $verifyType = VerificationType::forChannel($channel)->value;
        $where = ['user_id' => $userId, 'type' => $verifyType];

        $result = $this->otpService->verifyOtpDetailed($code, $where);

        if ($result['error'] !== null) {
            $errorEnum = AuthErrorCode::tryFrom($result['error']);
            return OperationResult::fail($errorEnum ?? $result['error'], match ($result['error']) {
                'no_verification' => sprintf(__('No pending %s verification.', 'wp-sms'), $channel),
                'expired'         => __('Verification code has expired.', 'wp-sms'),
                'max_attempts'    => __('Too many attempts.', 'wp-sms'),
                'invalid_code'    => __('Invalid verification code.', 'wp-sms'),
                default           => __('Verification failed.', 'wp-sms'),
            });
        }

        // Apply channel-specific post-verification actions.
        $this->applyChannelVerified($userId, $channel, $result['verification']->identifier);
        $this->maybeActivateUser($userId);

        $channelLabel = ucfirst($channel);

        return OperationResult::ok(sprintf(__('%s verified successfully.', 'wp-sms'), $channelLabel));
    }

    /**
     * Resend a verification code/link for the given channel.
     */
    public function resendVerification(int $userId, string $channel): OperationResult
    {
        $identifier = $this->getChannelIdentifier($userId, $channel);

        if ($identifier === null) {
            return OperationResult::fail("no_{$channel}", sprintf(__('No %s on file.', 'wp-sms'), $channel));
        }

        $settings = $this->settingsRepo->all();
        $verifyType = VerificationType::forChannel($channel)->value;
        $cooldown = (int) ($settings[$channel]['cooldown'] ?? 60);

        if ($this->isVerificationOnCooldown($userId, $verifyType, $cooldown)) {
            return OperationResult::fail(AuthErrorCode::Cooldown, __('Please wait before requesting a new code.', 'wp-sms'));
        }

        $this->invalidateVerifications($userId, $verifyType);
        $sent = $this->createChannelVerification($userId, $channel, $identifier);

        if (!$sent) {
            return OperationResult::fail(
                AuthErrorCode::ChannelUnavailable,
                __('Could not send verification code. No messaging gateway is configured.', 'wp-sms')
            );
        }

        return OperationResult::ok(__('Verification resent.', 'wp-sms'));
    }

    /**
     * Get the verification status for a user.
     *
     * @return array{pending_verifications: array, all_verified: bool}
     */
    public function getVerificationStatus(int $userId): array
    {
        $state = UserInfo::getUserVerificationState($userId);
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
        $settings = $this->settingsRepo->all();
        if (empty($settings[$channel]['enabled'])) {
            return;
        }

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
            $pending = get_user_meta($userId, UserMeta::PENDING_PHONE, true);
            if (!empty($pending)) {
                return $pending;
            }
        }

        if ($channel === 'email') {
            $pending = get_user_meta($userId, UserMeta::PENDING_EMAIL, true);
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
            $phone = get_user_meta($userId, UserMeta::PHONE, true);
            return !empty($phone) ? $phone : null;
        }

        if ($channel === 'email') {
            $email = get_userdata($userId)?->user_email;
            return (!empty($email) && !UserInfo::isPlaceholderEmail($email)) ? $email : null;
        }

        // For future channels, check user meta by convention: wsms_{channel}.
        $value = get_user_meta($userId, 'wsms_' . $channel, true);
        return !empty($value) ? $value : null;
    }

    /**
     * Create a verification record for any channel and deliver it.
     */
    /**
     * @return bool Whether the verification was actually created and sent.
     */
    private function createChannelVerification(int $userId, string $channel, string $identifier): bool
    {
        if ($channel === 'phone' && $this->sendingPolicyGuardResolver !== null) {
            $restriction = ($this->sendingPolicyGuardResolver)()->isAllowedForAuth($identifier);

            if (!$restriction->allowed) {
                return false;
            }
        }

        if ($channel === 'email' && !$this->emailUsesOtp()) {
            $this->createVerification($userId, VerificationType::EmailVerify->value, $identifier);

            return true;
        }

        $otp = $this->createOtpVerification($userId, $channel, $identifier);
        $settings = $this->settingsRepo->all();
        $expiry = (int) (($settings[$channel] ?? [])['expiry'] ?? 300);

        if ($channel === 'phone') {
            $deliveryChannel = $settings['phone']['delivery_channel'];
            $message = $this->templateManager->renderToMessage(
                TemplateType::PhoneVerification->value,
                $deliveryChannel,
                $identifier,
                [
                    'otp_code'       => $otp,
                    'expiry_minutes' => (string) (int) ($expiry / 60),
                ],
                [
                    'purpose'  => 'otp',
                    'otp_code' => $otp,
                    'expiry'   => $expiry,
                ],
            );
            $result = $this->messageDispatcher->sendImmediate($message, $this->getOtpGatewayId('phone'));

            if (!$result->success) {
                return false;
            }
        } elseif ($channel === 'email') {
            $message = $this->templateManager->renderToMessage(
                TemplateType::Otp->value,
                'email',
                $identifier,
                [
                    'otp_code'       => $otp,
                    'expiry_minutes' => (string) (int) ($expiry / 60),
                ],
            );
            $result = $this->messageDispatcher->sendImmediate($message, $this->getOtpGatewayId('email'));

            if (!$result->success) {
                return false;
            }
        }

        return true;
    }

    private function checkPhoneRestriction(string $phone): ?OperationResult
    {
        if ($phone === '' || $this->sendingPolicyGuardResolver === null) {
            return null;
        }

        $restriction = ($this->sendingPolicyGuardResolver)()->isAllowedForAuth($phone);

        if ($restriction->allowed) {
            return null;
        }

        return OperationResult::fail(AuthErrorCode::PhoneRestricted, $restriction->message);
    }

    private function savePhoneDirectly(int $userId, string $phone): void
    {
        delete_user_meta($userId, UserMeta::PENDING_PHONE);
        update_user_meta($userId, UserMeta::PHONE, $phone);
        update_user_meta($userId, UserMeta::PHONE_VERIFIED, '0');
    }

    private function saveEmailDirectly(int $userId, string $email): void
    {
        delete_user_meta($userId, UserMeta::PENDING_EMAIL);
        wp_update_user(['ID' => $userId, 'user_email' => $email]);
        update_user_meta($userId, UserMeta::EMAIL_VERIFIED, '0');
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
        $settings = $this->settingsRepo->all();
        $channelSettings = $settings[$channel] ?? [];

        return $this->otpService->createOtp(
            $userId,
            VerificationType::forChannel($channel)->value,
            $identifier,
            (int) ($channelSettings['code_length'] ?? 6),
            (int) ($channelSettings['expiry'] ?? 300),
            (int) ($channelSettings['max_attempts'] ?? 3),
        );
    }

    /**
     * Whether the email channel is configured for OTP verification.
     */
    public function emailUsesOtp(): bool
    {
        $settings = $this->settingsRepo->all();
        $methods = (array) $settings['email']['verification_methods'];

        return in_array('otp', $methods, true);
    }

    /**
     * Transition a pending user to active if all required verifications are complete
     * (or if the admin has since disabled verify_at_signup).
     */
    public function maybeActivateUser(int $userId): void
    {
        $status = get_user_meta($userId, UserMeta::REGISTRATION_STATUS, true);
        if ($status !== 'pending') {
            return;
        }

        $settings = $this->settingsRepo->all();
        $state = UserInfo::getUserVerificationState($userId);

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
        update_user_meta($userId, UserMeta::REGISTRATION_STATUS, 'active');
        delete_user_meta($userId, UserMeta::REGISTRATION_CREATED_AT);
        $this->sendWelcomeMessage($userId);
    }

    private function sendWelcomeMessage(int $userId): void
    {
        if (!$this->templateManager->isEnabled(TemplateType::Welcome->value)) {
            return;
        }

        $user = get_userdata($userId);
        if (!$user || UserInfo::isPlaceholderEmail($user->user_email)) {
            return;
        }

        try {
            $settings = $this->settingsRepo->all();
            $authBase = $settings['auth_base_path'] ?? '/account';
            $loginUrl = get_site_url() . $authBase . '/login';

            $message = $this->templateManager->renderToMessage(
                TemplateType::Welcome->value,
                'email',
                $user->user_email,
                [
                    'user_name' => $user->display_name,
                    'login_url' => $loginUrl,
                ],
            );
            $this->messageDispatcher->sendImmediate($message);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logger?->error('Failed to send welcome email: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    private function deleteExpiredPendingUser($user, int $ttlHours): void
    {
        if (!$user) {
            return;
        }

        $status = get_user_meta($user->ID, UserMeta::REGISTRATION_STATUS, true);
        $createdAt = get_user_meta($user->ID, UserMeta::REGISTRATION_CREATED_AT, true);

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
        update_user_meta($userId, UserMeta::EMAIL_VERIFIED, '1');
        delete_user_meta($userId, UserMeta::EMAIL_PLACEHOLDER);

        $pendingEmail = get_user_meta($userId, UserMeta::PENDING_EMAIL, true);

        if (!empty($pendingEmail) && $pendingEmail === $verifiedAddress) {
            wp_update_user([
                'ID'         => $userId,
                'user_email' => $pendingEmail,
            ]);
            delete_user_meta($userId, UserMeta::PENDING_EMAIL);
        }
    }

    /**
     * Mark phone as verified and apply any pending phone change.
     */
    private function markPhoneVerified(int $userId, string $verifiedPhone): void
    {
        update_user_meta($userId, UserMeta::PHONE_VERIFIED, '1');

        $pendingPhone = get_user_meta($userId, UserMeta::PENDING_PHONE, true);

        if (!empty($pendingPhone) && $pendingPhone === $verifiedPhone) {
            update_user_meta($userId, UserMeta::PHONE, $pendingPhone);
            delete_user_meta($userId, UserMeta::PENDING_PHONE);

            // Sync MFA phone factor if enrolled.
            $this->mfaManager->updateFactorMeta($userId, 'phone', ['phone' => $pendingPhone]);
        }
    }

    /**
     * Cancel a pending phone or email change.
     */
    public function cancelPendingChange(int $userId, string $channel): void
    {
        $metaKey = match ($channel) {
            'phone' => UserMeta::PENDING_PHONE,
            'email' => UserMeta::PENDING_EMAIL,
        };
        delete_user_meta($userId, $metaKey);
        $this->invalidateVerifications($userId, VerificationType::forChannel($channel)->value);
    }

    /**
     * Look up, validate, and consume a verification token.
     *
     * @return \stdClass|OperationResult The verification record on success, or an error result on failure.
     */
    private function consumeVerification(string $token, string $type): \stdClass|OperationResult
    {
        $result = $this->otpService->consumeTokenDetailed($token, $type);

        if ($result['error'] !== null) {
            $errorEnum = AuthErrorCode::tryFrom($result['error']);
            return OperationResult::fail($errorEnum ?? $result['error'], match ($result['error']) {
                'expired_token'  => __('This token has expired.', 'wp-sms'),
                'used_token'     => __('This token has already been used.', 'wp-sms'),
                default          => __('Invalid or expired token.', 'wp-sms'),
            });
        }

        return $result['verification'];
    }

    /**
     * Create a verification record and send notification.
     */
    private function createVerification(int $userId, string $type, string $identifier): void
    {
        $token = $this->otpService->createToken($userId, $type, $identifier, 3600);

        $baseUrl = get_site_url();
        $authSettings = $this->settingsRepo->all();
        $authBase = $authSettings['auth_base_url'] ?? '/account';

        if ($type === VerificationType::EmailVerify->value) {
            $link = $baseUrl . $authBase . '/verify-email?token=' . $token;
            $message = $this->templateManager->renderToMessage(
                TemplateType::EmailVerification->value,
                'email',
                $identifier,
                [
                    'verify_url'     => $link,
                    'expiry_minutes' => '60',
                ],
            );
        } else {
            $link = $baseUrl . $authBase . '/reset-password?token=' . $token;
            $message = $this->templateManager->renderToMessage(
                TemplateType::PasswordReset->value,
                'email',
                $identifier,
                [
                    'reset_url'      => $link,
                    'expiry_minutes' => '60',
                ],
            );
        }

        $this->messageDispatcher->sendImmediate($message);
    }

    private function isVerificationOnCooldown(int $userId, string $type, int $cooldownSeconds = 60): bool
    {
        return $this->otpService->isOnCooldown([
            'user_id' => $userId,
            'type'    => $type,
        ], $cooldownSeconds);
    }

    private function invalidateVerifications(int $userId, string $type): void
    {
        $this->otpService->invalidatePending([
            'user_id' => $userId,
            'type'    => $type,
        ]);
    }

    private function getOtpGatewayId(string $channel): ?string
    {
        $gatewayId = $this->settingsRepo->channel($channel)['otp_gateway'] ?? null;

        return !empty($gatewayId) ? $gatewayId : null;
    }
}
