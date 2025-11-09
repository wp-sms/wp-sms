<?php

namespace WP_SMS\Services\OTP\Assets;

/**
 * Authentication Assets Manager
 *
 * Handles global enqueuing of authentication assets that need to be available
 * on all pages (like modal JavaScript for triggers).
 */
class AuthAssets
{
    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Enqueue modal JS globally since it needs to handle triggers from anywhere
        add_action('wp_enqueue_scripts', [$this, 'enqueueGlobalAssets']);
        
        // Also enqueue on admin pages for potential admin usage
        add_action('admin_enqueue_scripts', [$this, 'enqueueGlobalAssets']);
    }

    /**
     * Enqueue global authentication assets
     */
    public function enqueueGlobalAssets(): void
    {
        // Modal JavaScript - needs to be available everywhere for modal triggers
        wp_enqueue_script(
            'wp-sms-auth-modal', 
            WP_SMS_URL . 'frontend/build-legacy/auth-modal.js', 
            ['wp-sms-auth-form'], // Depends on auth-form.js
            WP_SMS_VERSION, 
            true
        );

        // Auth form JavaScript - needed as dependency for modal
        wp_enqueue_script(
            'wp-sms-auth-form', 
            WP_SMS_URL . 'frontend/build-legacy/auth-form.js', 
            [], 
            WP_SMS_VERSION, 
            true
        );

        // Auth form CSS - needed for modal styling
        wp_enqueue_style(
            'wp-sms-auth-form', 
            WP_SMS_URL . 'frontend/build-legacy/assets/auth-form-styles.css', 
            [], 
            WP_SMS_VERSION
        );

        // Get redirect configuration
        $redirectConfig = \WP_SMS\Services\OTP\Helpers\RedirectHelper::getRedirectConfiguration();
        $redirectTo = \WP_SMS\Services\OTP\Helpers\RedirectHelper::getRedirectToFromRequest();

        // Localize script with necessary data
        wp_localize_script('wp-sms-auth-form', 'wpsmsAuthConfig', [
            'endpoints' => [
                'register' => [
                    'init'          => rest_url('wpsms/v1/register/init'),
                    'start'         => rest_url('wpsms/v1/register/start'),
                    'verify'        => rest_url('wpsms/v1/register/verify'),
                    'addIdentifier' => rest_url('wpsms/v1/register/add-identifier'),
                ],
                'login' => [
                    'init'         => rest_url('wpsms/v1/login/init'),
                    'start'        => rest_url('wpsms/v1/login/start'),
                    'verify'       => rest_url('wpsms/v1/login/verify'),
                    'mfaChallenge' => rest_url('wpsms/v1/login/mfa-challenge'),
                    'mfaVerify'    => rest_url('wpsms/v1/login/mfa-verify'),
                ],
                'passwordReset' => [
                    'init'     => rest_url('wpsms/v1/password-reset/init'),
                    'verify'   => rest_url('wpsms/v1/password-reset/verify'),
                    'complete' => rest_url('wpsms/v1/password-reset/complete'),
                ],
            ],
            'nonces' => [
                'rest' => wp_create_nonce('wp_rest'),
                'auth' => wp_create_nonce('wpsms_auth'),
            ],
            'redirects' => [
                'login'              => $redirectConfig['login_redirect'],
                'register'           => $redirectConfig['register_redirect'],
                'redirectTo'         => $redirectTo,
                'preserveRedirectTo' => $redirectConfig['preserve_redirect_to'],
                'autoLogin'          => $redirectConfig['auto_login_after_register'],
            ],
            'strings' => [
                'loading'              => __('Loading...', 'wp-sms'),
                'error'                => __('An error occurred.', 'wp-sms'),
                'close'                => __('Close', 'wp-sms'),
                'verifying'            => __('Verifying...', 'wp-sms'),
                'sending'              => __('Sending...', 'wp-sms'),
                'loginSuccess'         => __('Login successful! Redirecting...', 'wp-sms'),
                'registerSuccess'      => __('Registration successful! Redirecting...', 'wp-sms'),
                'invalidCode'          => __('Invalid verification code', 'wp-sms'),
                'expiredCode'          => __('Verification code has expired', 'wp-sms'),
                'rateLimited'          => __('Too many attempts. Please try again later.', 'wp-sms'),
                'networkError'         => __('Network error. Please check your connection.', 'wp-sms'),
                'skip'                 => __('Skip for now', 'wp-sms'),
                'addIdentifier'        => __('Add another identifier', 'wp-sms'),
                'complete'             => __('Complete', 'wp-sms'),
                'selectMfaMethod'      => __('Select authentication method', 'wp-sms'),
                'verifyMfa'            => __('Verify MFA', 'wp-sms'),
                'resendCode'           => __('Resend Code', 'wp-sms'),
                'changeIdentifier'     => __('Use different identifier', 'wp-sms'),
                'forgotPassword'       => __('Forgot Password?', 'wp-sms'),
                'resetPassword'        => __('Reset Password', 'wp-sms'),
                'newPassword'          => __('New Password', 'wp-sms'),
                'confirmPassword'      => __('Confirm Password', 'wp-sms'),
                'passwordResetSuccess' => __('Password reset successful!', 'wp-sms'),
                'backToLogin'          => __('Back to Login', 'wp-sms'),
                'enterNewPassword'     => __('Enter your new password', 'wp-sms'),
            ],
        ]);

        // Legacy compatibility
        wp_localize_script('wp-sms-auth-modal', 'wpsmsAuthData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wpsms/v1/'),
            'nonce'   => wp_create_nonce('wpsms_auth'),
        ]);

        // WordPress API settings
        wp_localize_script('wp-sms-auth-form', 'wpApiSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
            'root'  => esc_url_raw(rest_url()),
        ]);
    }
}
