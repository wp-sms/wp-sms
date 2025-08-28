<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Auth;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\Helpers\Response;
use WP_SMS\Services\OTP\Helpers\Username;
use WP_SMS\Services\OTP\Hooks\AuthHooks;
use WP_SMS\Services\OTP\Security\RateLimiter;

/**
 * Authentication REST API Endpoints
 *
 * Provides endpoints for login and registration.
 */
class AuthRestAPIEndpoints
{
    protected RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/auth/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpsms/v1', '/auth/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'register'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle user login
     */
    public function login(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Verify nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'wpsms_auth')) {
            return new WP_Error('invalid_nonce', __('Security check failed.', 'wp-sms'), ['status' => 403]);
        }

        $identifier = $request->get_param('identifier');
        $password = $request->get_param('password');
        $ip = $this->getClientIp($request);

        // Validate inputs
        if (empty($identifier) || empty($password)) {
            return new WP_Error('missing_fields', __('Username/email/phone and password are required.', 'wp-sms'), ['status' => 400]);
        }

        // Rate limiting
        $rateKey = 'auth:login:' . md5($ip);
        if (!$this->rateLimiter->isAllowed($rateKey, 5, 300)) {
            return new WP_Error('rate_limited', __('Too many login attempts. Please try again later.', 'wp-sms'), ['status' => 429]);
        }

        // Find user by identifier
        $user = $this->findUserByIdentifier($identifier);
        if (!$user) {
            $this->rateLimiter->increment($rateKey);
            AuthHooks::fireAuthFailure(['code' => 'user_not_found'], ['identifier' => $identifier, 'ip' => $ip]);
            return new WP_Error('invalid_credentials', __('Invalid username or password.', 'wp-sms'), ['status' => 401]);
        }

        // Verify password
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            $this->rateLimiter->increment($rateKey);
            AuthHooks::fireAuthFailure(['code' => 'invalid_password'], ['user_id' => $user->ID, 'ip' => $ip]);
            return new WP_Error('invalid_credentials', __('Invalid username or password.', 'wp-sms'), ['status' => 401]);
        }

        // Login successful
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        AuthHooks::fireAuthSuccess($user->ID, ['method' => 'password', 'ip' => $ip]);

        return new WP_REST_Response(Response::success(__('Login successful.', 'wp-sms'), [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
        ]));
    }

    /**
     * Handle user registration
     */
    public function register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Verify nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'wpsms_auth')) {
            return new WP_Error('invalid_nonce', __('Security check failed.', 'wp-sms'), ['status' => 403]);
        }

        $username = $request->get_param('username');
        $email = $request->get_param('email');
        $phone = $request->get_param('phone');
        $password = $request->get_param('password');
        $ip = $this->getClientIp($request);

        // Rate limiting
        $rateKey = 'auth:register:' . md5($ip);
        if (!$this->rateLimiter->isAllowed($rateKey, 3, 600)) {
            return new WP_Error('rate_limited', __('Too many registration attempts. Please try again later.', 'wp-sms'), ['status' => 429]);
        }

        // Validate required fields
        $errors = [];
        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!is_email($email)) {
            $errors['email'] = 'Invalid email format.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if (!empty($errors)) {
            return new WP_REST_Response(Response::validationError($errors), 400);
        }

        // Check if email already exists
        if (email_exists($email)) {
            return new WP_REST_Response(Response::error(__('Email already registered.', 'wp-sms'), 'email_exists'), 409);
        }

        // Generate username if not provided
        if (empty($username)) {
            $username = Username::fromEmail($email);
        } elseif (username_exists($username)) {
            return new WP_REST_Response(Response::error(__('Username already taken.', 'wp-sms'), 'username_exists'), 409);
        }

        // Create user
        $user_data = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => 'subscriber',
        ];

        // Add phone if provided
        if (!empty($phone)) {
            $user_data['meta_input'] = ['phone' => sanitize_text_field($phone)];
        }

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            $this->rateLimiter->increment($rateKey);
            return new WP_Error('registration_failed', __('Registration failed. Please try again.', 'wp-sms'), ['status' => 500]);
        }

        // Auto-login if no verification required
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        $this->rateLimiter->increment($rateKey);
        AuthHooks::fireAuthSuccess($user_id, ['method' => 'registration', 'ip' => $ip]);

        return new WP_REST_Response(Response::success(__('Registration successful.', 'wp-sms'), [
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email,
        ]));
    }

    /**
     * Find user by identifier (username, email, or phone)
     */
    protected function findUserByIdentifier(string $identifier): ?object
    {
        // Try username first
        $user = get_user_by('login', $identifier);
        if ($user) {
            return $user;
        }

        // Try email
        $user = get_user_by('email', $identifier);
        if ($user) {
            return $user;
        }

        // Try phone number (meta field)
        $users = get_users([
            'meta_key' => 'phone',
            'meta_value' => $identifier,
            'number' => 1,
        ]);

        if (!empty($users)) {
            return $users[0];
        }

        return null;
    }

    /**
     * Get client IP address
     */
    protected function getClientIp(WP_REST_Request $request): string
    {
        $ip = $request->get_header('X-Forwarded-For');
        if (!$ip) {
            $ip = $request->get_header('X-Real-IP');
        }
        if (!$ip) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // Handle multiple IPs in X-Forwarded-For
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
