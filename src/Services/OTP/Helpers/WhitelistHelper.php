<?php

namespace WP_SMS\Services\OTP\Helpers;

use WP_SMS\Option;

/**
 * Whitelist Helper
 *
 * Manages IP whitelist for bypassing security restrictions
 */
class WhitelistHelper
{
    /**
     * Check if IP whitelist is enabled
     */
    public static function isWhitelistEnabled(): bool
    {
        return (bool) Option::getOption('otp_ip_whitelist_enabled', false, false);
    }

    /**
     * Check if an IP address is whitelisted
     */
    public static function isWhitelisted(string $ip): bool
    {
        if (!self::isWhitelistEnabled()) {
            return false;
        }

        $whitelistedIps = self::getWhitelistedIps();
        
        if (empty($whitelistedIps)) {
            return false;
        }

        foreach ($whitelistedIps as $whitelistEntry) {
            if (self::ipMatches($ip, $whitelistEntry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get whitelisted IP addresses
     */
    public static function getWhitelistedIps(): array
    {
        $addresses = Option::getOption('otp_ip_whitelist_addresses', false, '');
        
        if (empty($addresses)) {
            return [];
        }

        // Split by newlines and clean up
        $lines = explode("\n", $addresses);
        $ips = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Validate IP or CIDR
            if (self::isValidIpOrCidr($line)) {
                $ips[] = $line;
            }
        }

        return $ips;
    }

    /**
     * Check if IP should bypass rate limiting
     */
    public static function shouldBypassRateLimit(string $ip): bool
    {
        if (!self::isWhitelisted($ip)) {
            return false;
        }

        return (bool) Option::getOption('otp_ip_whitelist_bypass_rate_limit', false, true);
    }

    /**
     * Check if IP should bypass MFA
     */
    public static function shouldBypassMfa(string $ip): bool
    {
        if (!self::isWhitelisted($ip)) {
            return false;
        }

        return (bool) Option::getOption('otp_ip_whitelist_bypass_mfa', false, false);
    }

    /**
     * Check if whitelist bypasses should be logged
     */
    public static function shouldLogBypasses(): bool
    {
        return (bool) Option::getOption('otp_ip_whitelist_log_bypasses', false, true);
    }

    /**
     * Validate if string is a valid IP address or CIDR notation
     */
    private static function isValidIpOrCidr(string $input): bool
    {
        // Check if it's a CIDR notation
        if (strpos($input, '/') !== false) {
            list($ip, $mask) = explode('/', $input, 2);
            
            // Validate IP part
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }

            // Validate mask
            $mask = (int) $mask;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // IPv4: mask should be 0-32
                return $mask >= 0 && $mask <= 32;
            } else {
                // IPv6: mask should be 0-128
                return $mask >= 0 && $mask <= 128;
            }
        }

        // Check if it's a regular IP address
        return filter_var($input, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if an IP matches a whitelist entry (supports CIDR)
     */
    private static function ipMatches(string $ip, string $whitelistEntry): bool
    {
        // Exact match
        if ($ip === $whitelistEntry) {
            return true;
        }

        // CIDR notation
        if (strpos($whitelistEntry, '/') !== false) {
            return self::ipInCidr($ip, $whitelistEntry);
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr, 2);

        // Check if IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);
            
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // Check if IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            
            return self::ipv6InCidr($ip, $subnet, (int) $mask);
        }

        return false;
    }

    /**
     * Check if IPv6 address is within a CIDR range
     */
    private static function ipv6InCidr(string $ip, string $subnet, int $mask): bool
    {
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        // Convert to binary strings
        $ipBits = '';
        $subnetBits = '';

        for ($i = 0; $i < strlen($ipBinary); $i++) {
            $ipBits .= str_pad(decbin(ord($ipBinary[$i])), 8, '0', STR_PAD_LEFT);
            $subnetBits .= str_pad(decbin(ord($subnetBinary[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Compare first $mask bits
        return substr($ipBits, 0, $mask) === substr($subnetBits, 0, $mask);
    }

    /**
     * Get whitelist statistics
     */
    public static function getWhitelistStats(): array
    {
        $ips = self::getWhitelistedIps();
        
        $ipv4Count = 0;
        $ipv6Count = 0;
        $cidrCount = 0;

        foreach ($ips as $entry) {
            if (strpos($entry, '/') !== false) {
                $cidrCount++;
                list($ip, $mask) = explode('/', $entry, 2);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipv4Count++;
                } else {
                    $ipv6Count++;
                }
            } else {
                if (filter_var($entry, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipv4Count++;
                } else {
                    $ipv6Count++;
                }
            }
        }

        return [
            'total' => count($ips),
            'ipv4' => $ipv4Count,
            'ipv6' => $ipv6Count,
            'cidr_ranges' => $cidrCount,
        ];
    }

    /**
     * Validate and sanitize whitelist input
     */
    public static function validateWhitelistInput(string $input): array
    {
        $lines = explode("\n", $input);
        $valid = [];
        $invalid = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (self::isValidIpOrCidr($line)) {
                $valid[] = $line;
            } else {
                $invalid[] = $line;
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }
}

