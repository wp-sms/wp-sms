# IP Whitelist Feature Documentation

**Version:** 1.0.0  
**Last Updated:** November 2025

---

## Table of Contents

1. [Overview](#overview)
2. [Purpose & Use Cases](#purpose--use-cases)
3. [Architecture](#architecture)
4. [Configuration](#configuration)
5. [API Reference](#api-reference)
6. [Integration Points](#integration-points)
7. [Security Considerations](#security-considerations)
8. [Examples](#examples)
9. [Troubleshooting](#troubleshooting)
10. [Changelog](#changelog)

---

## Overview

The IP Whitelist feature allows administrators to define a list of trusted IP addresses that are exempt from certain security mechanisms, such as rate limiting, CAPTCHA, or OTP/MFA challenges. This is particularly useful for internal teams, trusted partners, or secure office networks, ensuring that authentication flows remain smooth while still maintaining high security for the general public.

### Key Features

- **Flexible IP Management**: Support for individual IPv4/IPv6 addresses and CIDR ranges
- **Selective Bypass**: Configure which security mechanisms whitelisted IPs bypass
- **Simple Configuration**: Textarea-based configuration in admin settings - frontend handles all management
- **Automatic Logging**: Optional logging of whitelist bypass events
- **Real-time Validation**: IP addresses are validated on every request
- **Seamless Integration**: Automatically integrated into rate limiting and MFA flows

---

## Purpose & Use Cases

### Primary Use Cases

1. **Internal Team Access**
   - Office networks can bypass rate limiting for smoother internal operations
   - Development/staging environments can be whitelisted for testing

2. **Trusted Partners**
   - Partner integrations from known IP ranges
   - Third-party services that need reliable access

3. **Testing & Debugging**
   - QA teams can perform repeated authentication tests without hitting rate limits
   - Development environments can bypass MFA for faster iteration

4. **High-Security Networks**
   - Corporate networks with existing security measures
   - VPN endpoints with guaranteed secure access

### Bypass Options

Whitelisted IPs can be configured to bypass:

- **Rate Limiting**: Skip request throttling (default: enabled)
- **MFA Requirements**: Skip multi-factor authentication (default: disabled)
- **Future Features**: CAPTCHA, geographic restrictions, etc.

---

## Architecture

### Components

```
┌─────────────────────────────────────────────────────────┐
│                    IP Whitelist System                   │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────────┐        ┌──────────────────┐      │
│  │  OTPSettings.php │───────▶│ WhitelistHelper  │      │
│  │  (Admin Config)  │        │  (Core Logic)    │      │
│  └──────────────────┘        └──────────────────┘      │
│                                         │               │
│                                         │               │
│                                         ▼               │
│                        ┌──────────────────────────┐    │
│                        │ RestAPIEndpointsAbstract │    │
│                        │  (Integration Layer)      │    │
│                        └──────────────────────────┘    │
│                                         │               │
│                 ┌───────────────────────┼───────────┐  │
│                 │                       │           │  │
│                 ▼                       ▼           ▼  │
│        ┌─────────────┐        ┌──────────────┐ ┌────┐│
│        │Rate Limiter │        │ MFA Check    │ │Log ││
│        │   Bypass    │        │   Bypass     │ │    ││
│        └─────────────┘        └──────────────┘ └────┘│
│                                                        │
└────────────────────────────────────────────────────────┘
```

### File Structure

```
wp-content/plugins/wp-sms/src/
├── Settings/Groups/
│   └── OTPSettings.php                       # Admin UI configuration & settings
├── Services/OTP/
│   ├── Helpers/
│   │   └── WhitelistHelper.php              # Core whitelist logic
│   └── RestAPIEndpoints/
│       └── Abstracts/
│           └── RestAPIEndpointsAbstract.php # Integration point (rate limiting, MFA)
```

### Database Storage

Whitelist configuration is stored in WordPress options:

| Option Key                           | Type    | Description                                    |
|--------------------------------------|---------|------------------------------------------------|
| `otp_ip_whitelist_enabled`           | boolean | Enable/disable whitelist feature               |
| `otp_ip_whitelist_addresses`         | text    | Newline-separated list of IPs/CIDR ranges      |
| `otp_ip_whitelist_bypass_rate_limit` | boolean | Whether to bypass rate limiting                |
| `otp_ip_whitelist_bypass_mfa`        | boolean | Whether to bypass MFA requirements             |
| `otp_ip_whitelist_log_bypasses`      | boolean | Whether to log whitelist bypass events         |

---

## Configuration

### Admin Settings (UI)

Navigate to: **WP-SMS → Settings → OTP Settings → IP Whitelist**

#### Available Settings

1. **Enable IP Whitelist**
   - Checkbox to enable/disable the entire feature
   - When disabled, all IPs are treated normally

2. **Whitelisted IP Addresses**
   - Textarea with one IP per line
   - Supports IPv4, IPv6, and CIDR notation
   - Lines starting with `#` are treated as comments
   - Example:
     ```
     # Office network
     192.168.1.100
     10.0.0.0/8
     
     # Partner API
     203.0.113.0/24
     
     # IPv6 support
     2001:db8::/32
     ```

3. **Bypass Rate Limiting**
   - Default: `true`
   - Whitelisted IPs skip rate limit checks

4. **Bypass MFA Requirements**
   - Default: `false`
   - Whitelisted IPs skip MFA challenges
   - **Security Warning**: Use with caution

5. **Log Whitelist Bypasses**
   - Default: `true`
   - Logs authentication events when whitelist rules apply
   - Useful for auditing and compliance

### Programmatic Configuration

```php
use WP_SMS\Option;

// Enable whitelist
Option::updateOption('otp_ip_whitelist_enabled', true);

// Add IP addresses
$ips = "192.168.1.100\n10.0.0.0/8\n2001:db8::/32";
Option::updateOption('otp_ip_whitelist_addresses', $ips);

// Configure bypass settings
Option::updateOption('otp_ip_whitelist_bypass_rate_limit', true);
Option::updateOption('otp_ip_whitelist_bypass_mfa', false);
Option::updateOption('otp_ip_whitelist_log_bypasses', true);
```

---

## API Reference

**Note:** The IP Whitelist feature does not expose REST API endpoints. All configuration is managed through the WordPress admin settings UI (`OTPSettings.php`), and the frontend handles saving/loading via the standard WordPress options API.

The whitelist functionality is automatically integrated into existing authentication endpoints through the `WhitelistHelper` class.

---

## Integration Points

### WhitelistHelper Methods

The `WhitelistHelper` class provides all core functionality:

```php
use WP_SMS\Services\OTP\Helpers\WhitelistHelper;

// Check if whitelist is enabled
$enabled = WhitelistHelper::isWhitelistEnabled();

// Check if an IP is whitelisted
$isWhitelisted = WhitelistHelper::isWhitelisted('192.168.1.100');

// Check if IP should bypass rate limiting
$shouldBypass = WhitelistHelper::shouldBypassRateLimit('192.168.1.100');

// Check if IP should bypass MFA
$shouldBypassMfa = WhitelistHelper::shouldBypassMfa('192.168.1.100');

// Get all whitelisted IPs
$ips = WhitelistHelper::getWhitelistedIps();

// Get statistics
$stats = WhitelistHelper::getWhitelistStats();

// Validate input
$validation = WhitelistHelper::validateWhitelistInput($userInput);
// Returns: ['valid' => [...], 'invalid' => [...]]
```

### Automatic Integration

The whitelist is automatically integrated into:

1. **Rate Limiting** (`RestAPIEndpointsAbstract::checkRateLimits()`)
   - Whitelisted IPs bypass rate limit checks
   - Rate limit counters are not incremented for whitelisted IPs

2. **MFA Challenges** (via `RestAPIEndpointsAbstract::shouldBypassMfa()`)
   - Optional bypass of MFA requirements
   - Must be explicitly enabled in settings

3. **Event Logging** (`RestAPIEndpointsAbstract::logAuthEvent()`)
   - Whitelist bypass information is automatically added to auth events
   - Includes `whitelisted`, `bypass_rate_limit`, `bypass_mfa` flags

### Custom Integration

To integrate whitelist checks in custom code:

```php
use WP_SMS\Services\OTP\Helpers\WhitelistHelper;

class MyCustomEndpoint {
    
    public function handleRequest($request) {
        $ip = $this->getClientIp($request);
        
        // Check if whitelisted
        if (WhitelistHelper::isWhitelisted($ip)) {
            // Apply custom logic for whitelisted IPs
            if (WhitelistHelper::shouldBypassRateLimit($ip)) {
                // Skip rate limiting
            }
            
            if (WhitelistHelper::shouldBypassMfa($ip)) {
                // Skip MFA
            }
        }
        
        // Continue with normal flow
    }
}
```

---

## Security Considerations

### Best Practices

1. **Minimize Whitelist Size**
   - Only whitelist truly trusted networks
   - Regularly audit and remove unused entries

2. **Use Specific Ranges**
   - Avoid whitelisting large CIDR blocks unless necessary
   - Prefer specific IPs over broad ranges

3. **MFA Bypass**
   - **Use with extreme caution**
   - Only enable for highly secure, controlled networks
   - Consider alternative authentication methods instead

4. **Regular Audits**
   - Enable logging (`otp_ip_whitelist_log_bypasses`)
   - Review auth event logs regularly
   - Monitor for suspicious patterns

5. **Network Security**
   - Ensure whitelisted networks have strong perimeter security
   - Use VPNs or secure tunnels where possible
   - Implement IP rotation policies

### Logging & Monitoring

When logging is enabled, all authentication events from whitelisted IPs include:

```json
{
  "whitelisted": true,
  "bypass_rate_limit": true,
  "bypass_mfa": false
}
```

Query auth events to monitor whitelist usage:

```php
use WP_SMS\Services\OTP\Models\AuthEventModel;

// Get all events from whitelisted IPs
$events = AuthEventModel::getByAdditionalData('whitelisted', true);

// Get events with MFA bypass
$mfaBypass = AuthEventModel::getByAdditionalData('bypass_mfa', true);
```

### Risks & Mitigations

| Risk                          | Mitigation                                     |
|-------------------------------|------------------------------------------------|
| IP spoofing                   | Use proxy/firewall headers validation         |
| Compromised whitelisted IP    | Regular audits, quick removal capability       |
| Overly broad CIDR ranges      | Validation alerts, admin review                |
| MFA bypass abuse              | Default disabled, extensive logging            |
| Insider threats               | Admin-only management, audit trails            |

---

## Examples

### Example 1: Whitelist Office Network

```php
// Admin adds office network via UI or API
$ips = "
# Main office
192.168.1.0/24

# Remote office VPN
10.0.100.0/24
";

Option::updateOption('otp_ip_whitelist_addresses', $ips);
Option::updateOption('otp_ip_whitelist_bypass_rate_limit', true);
Option::updateOption('otp_ip_whitelist_bypass_mfa', false); // Keep MFA for security
```

**Result:**
- Office employees can perform unlimited authentication attempts
- They still need to complete MFA
- All attempts are logged with whitelist flag

---

### Example 2: Partner API Integration

**Admin Configuration (via UI):**

Navigate to **WP-SMS → Settings → OTP Settings → IP Whitelist** and add:

```
# Partner integration
203.0.113.50
```

Enable "Bypass Rate Limiting" checkbox.

**Result:**
- No rate limiting for partner's API calls to authentication endpoints
- Smooth integration without throttling
- Logged events include whitelist information

---

### Example 3: Development Environment

```php
// Whitelist localhost and development IPs
$ips = "
127.0.0.1
::1
192.168.1.100
";

Option::updateOption('otp_ip_whitelist_addresses', $ips);
Option::updateOption('otp_ip_whitelist_bypass_rate_limit', true);
Option::updateOption('otp_ip_whitelist_bypass_mfa', true); // Dev only!
```

**Result:**
- Developers can test without rate limits
- MFA is skipped for faster iteration
- **Security Note:** Only use in non-production environments

---

### Example 4: IPv6 Corporate Network

```php
// Whitelist IPv6 range
$ips = "2001:db8:1234::/48";

Option::updateOption('otp_ip_whitelist_addresses', $ips);
Option::updateOption('otp_ip_whitelist_bypass_rate_limit', true);
```

**Result:**
- Entire corporate IPv6 range is whitelisted
- Future-proof for IPv6-only networks

---

## Troubleshooting

### Common Issues

#### 1. IP Not Being Recognized

**Symptoms:**
- IP should be whitelisted but rate limiting still applies
- `isWhitelisted()` returns `false`

**Solutions:**
- Verify IP format in the settings textarea
- Check for typos in CIDR notation
- Ensure whitelist feature is enabled (checkbox)
- Verify proxy headers are correctly configured
- Check for IPv4/IPv6 mismatch

**Debug:**
```php
$ip = '192.168.1.100';
echo "Enabled: " . WhitelistHelper::isWhitelistEnabled() . "\n";
echo "Whitelisted: " . WhitelistHelper::isWhitelisted($ip) . "\n";
echo "IPs: " . print_r(WhitelistHelper::getWhitelistedIps(), true);
```

---

#### 2. CIDR Range Not Working

**Symptoms:**
- Individual IPs work but CIDR ranges don't

**Solutions:**
- Verify CIDR notation syntax in textarea (e.g., `192.168.1.0/24`)
- Ensure no spaces around the `/`
- Validate mask is correct for IP version (0-32 for IPv4, 0-128 for IPv6)
- Save settings and verify they're persisted

**Example:**
```
# Correct
192.168.1.0/24
10.0.0.0/8

# Incorrect
192.168.1.0 / 24  ❌ (spaces)
192.168.1.0/33   ❌ (invalid mask)
```

---

#### 3. MFA Still Required After Bypass

**Symptoms:**
- `bypass_mfa` is enabled but MFA is still challenged

**Solutions:**
- Verify IP is actually whitelisted
- Check `otp_ip_whitelist_bypass_mfa` option is `true`
- Ensure endpoint is checking `shouldBypassMfa()`
- Review application flow logic

---

#### 4. Logging Not Working

**Symptoms:**
- Whitelist events not appearing in logs

**Solutions:**
- Enable `otp_ip_whitelist_log_bypasses` option
- Verify `AuthEventModel::insert()` is being called
- Check database for auth events table
- Review error logs for exceptions

---

### Validation Errors

```php
$validation = WhitelistHelper::validateWhitelistInput($input);

if (!empty($validation['invalid'])) {
    echo "Invalid IPs found:\n";
    foreach ($validation['invalid'] as $ip) {
        echo "  - $ip\n";
    }
}
```

Common validation failures:
- Invalid IP format
- Invalid CIDR mask
- Mixed IPv4/IPv6 in same CIDR
- Non-IP strings

---

## Changelog

### Version 1.0.0 (November 2025)

**Initial Release**

- ✅ Core whitelist functionality
- ✅ IPv4 and IPv6 support
- ✅ CIDR notation support
- ✅ Rate limiting bypass
- ✅ Optional MFA bypass
- ✅ Admin settings UI
- ✅ Full REST API
- ✅ Automatic event logging
- ✅ Integration with existing auth system
- ✅ Comprehensive validation
- ✅ Real-time IP checking

**Components Added:**
- `OTPSettings.php` - Admin configuration with textarea-based UI
- `WhitelistHelper.php` - Core logic and IP validation
- Integration with `RestAPIEndpointsAbstract` (rate limiting & MFA)
- Authentication event logging enhancement
- Automatic integration into all auth endpoints

**Future Enhancements (Planned):**
- Geographic IP whitelisting
- Time-based whitelist rules
- Whitelist groups/tags
- Import/export functionality
- Enhanced UI for viewing whitelist logs
- Real-time IP testing tool in admin
- Webhook notifications for whitelist bypass events

---

## Support & Maintenance

### Getting Help

- Review this documentation
- Check troubleshooting section
- Review auth event logs in the database
- Test programmatically with `WhitelistHelper::isWhitelisted($ip)`

### Reporting Issues

When reporting issues, include:
1. WordPress version
2. WP-SMS plugin version
3. Example IP address (sanitized)
4. Expected vs actual behavior
5. Relevant error logs

---

**End of Documentation**

