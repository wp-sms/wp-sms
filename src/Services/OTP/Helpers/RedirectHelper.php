<?php

namespace WP_SMS\Services\OTP\Helpers;

use WP_SMS\Option;
use WP_User;

/**
 * Redirect Helper
 *
 * Manages post-authentication redirects for login and registration flows
 */
class RedirectHelper
{
    /**
     * Get redirect URL for a user after login
     *
     * @param int|WP_User $user User ID or WP_User object
     * @param string|null $shortcodeRedirect Redirect from shortcode attribute
     * @param string|null $redirectTo Redirect from query parameter
     * @return string
     */
    public static function getLoginRedirectUrl($user, ?string $shortcodeRedirect = null, ?string $redirectTo = null): string
    {
        // Convert to user object if needed
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user instanceof WP_User) {
            return home_url('/');
        }

        // Priority 1: Shortcode attribute (highest priority for specific implementations)
        if (!empty($shortcodeRedirect)) {
            return self::sanitizeRedirectUrl($shortcodeRedirect);
        }

        // Priority 2: ?redirect_to query parameter (if enabled)
        if (self::shouldPreserveRedirectTo() && !empty($redirectTo)) {
            return self::sanitizeRedirectUrl($redirectTo);
        }

        // Priority 3: Admin users to dashboard (if enabled)
        if (self::shouldRedirectAdminsToDashboard() && user_can($user, 'manage_options')) {
            return admin_url();
        }

        // Priority 4: Role-based redirects (if enabled)
        if (self::isRoleBasedRedirectsEnabled()) {
            $roleRedirect = self::getRoleRedirectUrl($user);
            if (!empty($roleRedirect)) {
                return $roleRedirect;
            }
        }

        // Priority 5: Global login redirect setting
        $globalRedirect = self::getGlobalLoginRedirect();
        if (!empty($globalRedirect)) {
            return $globalRedirect;
        }

        // Fallback: Homepage
        return home_url('/');
    }

    /**
     * Get redirect URL for a user after registration
     *
     * @param int|WP_User $user User ID or WP_User object
     * @param string|null $shortcodeRedirect Redirect from shortcode attribute
     * @return string
     */
    public static function getRegisterRedirectUrl($user, ?string $shortcodeRedirect = null): string
    {
        // Convert to user object if needed
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user instanceof WP_User) {
            return home_url('/');
        }

        // Priority 1: Shortcode attribute
        if (!empty($shortcodeRedirect)) {
            return self::sanitizeRedirectUrl($shortcodeRedirect);
        }

        // Priority 2: Admin users to dashboard (if enabled and auto-login is on)
        if (self::isAutoLoginAfterRegisterEnabled() && 
            self::shouldRedirectAdminsToDashboard() && 
            user_can($user, 'manage_options')) {
            return admin_url();
        }

        // Priority 3: Role-based redirects (if enabled and auto-login is on)
        if (self::isAutoLoginAfterRegisterEnabled() && self::isRoleBasedRedirectsEnabled()) {
            $roleRedirect = self::getRoleRedirectUrl($user);
            if (!empty($roleRedirect)) {
                return $roleRedirect;
            }
        }

        // Priority 4: Global register redirect setting
        $globalRedirect = self::getGlobalRegisterRedirect();
        if (!empty($globalRedirect)) {
            return $globalRedirect;
        }

        // Priority 5: Use login redirect as fallback
        $globalLoginRedirect = self::getGlobalLoginRedirect();
        if (!empty($globalLoginRedirect)) {
            return $globalLoginRedirect;
        }

        // Fallback: Homepage
        return home_url('/');
    }

    /**
     * Get global login redirect URL
     *
     * @return string
     */
    public static function getGlobalLoginRedirect(): string
    {
        $redirect = Option::getOption('otp_login_redirect_url', false, '');
        return self::sanitizeRedirectUrl($redirect);
    }

    /**
     * Get global register redirect URL
     *
     * @return string
     */
    public static function getGlobalRegisterRedirect(): string
    {
        $redirect = Option::getOption('otp_register_redirect_url', false, '');
        return self::sanitizeRedirectUrl($redirect);
    }

    /**
     * Check if role-based redirects are enabled
     *
     * @return bool
     */
    public static function isRoleBasedRedirectsEnabled(): bool
    {
        return (bool) Option::getOption('otp_enable_role_based_redirects', false, false);
    }

    /**
     * Get role-based redirect URL for a user
     *
     * @param WP_User $user
     * @return string|null
     */
    public static function getRoleRedirectUrl(WP_User $user): ?string
    {
        $roleRedirects = self::getRoleRedirects();
        
        if (empty($roleRedirects)) {
            return null;
        }

        // Check user's roles (users can have multiple roles)
        $userRoles = $user->roles;
        
        // Try to find a matching role redirect
        // First match wins (order matters)
        foreach ($userRoles as $role) {
            if (isset($roleRedirects[$role])) {
                return self::sanitizeRedirectUrl($roleRedirects[$role]);
            }
        }

        return null;
    }

    /**
     * Get all role redirect mappings
     *
     * @return array Format: ['role_name' => '/redirect-url']
     */
    public static function getRoleRedirects(): array
    {
        $roleRedirectsText = Option::getOption('otp_role_redirects', false, '');
        
        if (empty($roleRedirectsText)) {
            return [];
        }

        $redirects = [];
        $lines = explode("\n", $roleRedirectsText);

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse format: role_name|/redirect-url
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $role = trim($parts[0]);
                $url = trim($parts[1]);
                
                if (!empty($role) && !empty($url)) {
                    $redirects[$role] = $url;
                }
            }
        }

        return $redirects;
    }

    /**
     * Check if auto-login after registration is enabled
     *
     * @return bool
     */
    public static function isAutoLoginAfterRegisterEnabled(): bool
    {
        return (bool) Option::getOption('otp_auto_login_after_register', false, true);
    }

    /**
     * Check if ?redirect_to parameter should be preserved
     *
     * @return bool
     */
    public static function shouldPreserveRedirectTo(): bool
    {
        return (bool) Option::getOption('otp_preserve_redirect_to', false, true);
    }

    /**
     * Check if admins should always be redirected to dashboard
     *
     * @return bool
     */
    public static function shouldRedirectAdminsToDashboard(): bool
    {
        return (bool) Option::getOption('otp_admin_redirect_to_dashboard', false, true);
    }

    /**
     * Sanitize and validate redirect URL
     *
     * @param string $url
     * @return string
     */
    public static function sanitizeRedirectUrl(string $url): string
    {
        if (empty($url)) {
            return home_url('/');
        }

        // Remove whitespace
        $url = trim($url);

        // If it's a relative URL, make it absolute
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = home_url($url);
        }

        // Validate URL
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            return home_url('/');
        }

        // Security: Only allow redirects to same host or subdomains
        if (isset($parsedUrl['host'])) {
            $siteHost = parse_url(home_url(), PHP_URL_HOST);
            $redirectHost = $parsedUrl['host'];
            
            // Allow exact match or subdomains
            if ($redirectHost !== $siteHost && !self::isSubdomain($redirectHost, $siteHost)) {
                // External URL - use WordPress allowed redirect hosts filter
                $allowedHosts = apply_filters('allowed_redirect_hosts', [$siteHost]);
                
                if (!in_array($redirectHost, $allowedHosts)) {
                    // Not allowed, redirect to homepage
                    return home_url('/');
                }
            }
        }

        // Use WordPress built-in sanitization
        return esc_url_raw($url);
    }

    /**
     * Check if redirect host is a subdomain of site host
     *
     * @param string $redirectHost
     * @param string $siteHost
     * @return bool
     */
    private static function isSubdomain(string $redirectHost, string $siteHost): bool
    {
        // Check if redirect host ends with .sitehost (PHP 7.2 compatible)
        $suffix = '.' . $siteHost;
        return substr($redirectHost, -strlen($suffix)) === $suffix;
    }

    /**
     * Get redirect URL from current request
     *
     * @return string|null
     */
    public static function getRedirectToFromRequest(): ?string
    {
        // Check query parameter
        if (isset($_GET['redirect_to']) && !empty($_GET['redirect_to'])) {
            return sanitize_text_field(wp_unslash($_GET['redirect_to']));
        }

        // Check POST data
        if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
            return sanitize_text_field(wp_unslash($_POST['redirect_to']));
        }

        return null;
    }

    /**
     * Build redirect URL with preserved query parameters
     *
     * @param string $baseUrl
     * @param array $params Additional query parameters
     * @return string
     */
    public static function buildRedirectUrl(string $baseUrl, array $params = []): string
    {
        if (empty($params)) {
            return $baseUrl;
        }

        $parsedUrl = parse_url($baseUrl);
        $query = [];

        // Parse existing query string
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        // Merge with new parameters
        $query = array_merge($query, $params);

        // Rebuild URL
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '/';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        $url = $scheme . '://' . $host . $path;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        $url .= $fragment;

        return $url;
    }

    /**
     * Get redirect configuration summary
     *
     * @return array
     */
    public static function getRedirectConfiguration(): array
    {
        return [
            'login_redirect'              => self::getGlobalLoginRedirect(),
            'register_redirect'           => self::getGlobalRegisterRedirect(),
            'role_based_enabled'          => self::isRoleBasedRedirectsEnabled(),
            'role_redirects'              => self::getRoleRedirects(),
            'auto_login_after_register'   => self::isAutoLoginAfterRegisterEnabled(),
            'preserve_redirect_to'        => self::shouldPreserveRedirectTo(),
            'admin_to_dashboard'          => self::shouldRedirectAdminsToDashboard(),
        ];
    }

    /**
     * Validate role redirect configuration
     *
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateRoleRedirects(): array
    {
        $errors = [];
        $roleRedirects = self::getRoleRedirects();
        $validRoles = array_keys(wp_roles()->get_names());

        foreach ($roleRedirects as $role => $url) {
            // Check if role exists
            if (!in_array($role, $validRoles)) {
                $errors[] = sprintf(
                    __('Invalid role: %s', 'wp-sms'),
                    $role
                );
            }

            // Check if URL is valid
            if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                if (strpos($url, '/') !== 0) {
                    $errors[] = sprintf(
                        __('Invalid redirect URL for role %s: %s', 'wp-sms'),
                        $role,
                        $url
                    );
                }
            }
        }

        return $errors;
    }
}

