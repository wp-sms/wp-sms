# Redirect Management Documentation

**Version:** 1.0.0  
**Last Updated:** November 2025

---

## Table of Contents

1. [Overview](#overview)
2. [Redirect Priority System](#redirect-priority-system)
3. [Configuration](#configuration)
4. [Helper Methods](#helper-methods)
5. [Integration Examples](#integration-examples)
6. [Security](#security)
7. [Troubleshooting](#troubleshooting)
8. [Changelog](#changelog)

---

## Overview

The Redirect Management system controls where users are redirected after successfully logging in or registering through any supported authentication flow (OTP, Magic Link, Password). It provides flexible, hierarchical redirect logic with support for:

- **Global default redirects** (per action: login/register)
- **Role-based redirects** (e.g., Administrators → `/wp-admin`, Subscribers → `/dashboard`)
- **Shortcode attribute overrides** (e.g., `[wpsms_login_form redirect="/custom"]`)
- **Query parameter preservation** (e.g., `?redirect_to=/protected-page`)
- **Auto-login after registration**

---

## Redirect Priority System

The system uses a **priority-based waterfall** to determine the final redirect URL:

### Login Redirects

```
Priority 1: Shortcode attribute (highest)
    ↓
Priority 2: ?redirect_to query parameter (if enabled)
    ↓
Priority 3: Admin to dashboard (if user is admin and enabled)
    ↓
Priority 4: Role-based redirect (if enabled and role matches)
    ↓
Priority 5: Global login redirect setting
    ↓
Fallback: Homepage (/)
```

### Register Redirects

```
Priority 1: Shortcode attribute (highest)
    ↓
Priority 2: Admin to dashboard (if auto-login + admin + enabled)
    ↓
Priority 3: Role-based redirect (if auto-login + enabled + role matches)
    ↓
Priority 4: Global register redirect setting
    ↓
Priority 5: Global login redirect setting (fallback)
    ↓
Fallback: Homepage (/)
```

---

## Configuration

### Admin Settings

Navigate to: **WP-SMS → Settings → OTP Settings → Redirect Settings**

### Available Settings

#### 1. Default Login Redirect

```
Type: Text
Default: Empty (homepage)
Description: Where users are redirected after successful login
```

**Examples:**
- `/dashboard` - Custom dashboard page
- `/my-account` - Account page
- `/wp-admin` - WordPress admin (not recommended, use "Admins to Dashboard" instead)
- Empty - Homepage

---

#### 2. Default Register Redirect

```
Type: Text
Default: Empty (homepage)
Description: Where users are redirected after successful registration
```

**Examples:**
- `/welcome` - Welcome page
- `/onboarding` - Onboarding wizard
- `/my-account` - Account setup page
- Empty - Homepage

---

#### 3. Enable Role-Based Redirects

```
Type: Checkbox
Default: Disabled
Description: Redirect users based on their WordPress role
```

When enabled, you can configure different redirect URLs for each role.

---

#### 4. Role-Based Redirect URLs

```
Type: Textarea
Format: role_name|/redirect-url (one per line)
Visible: When "Enable Role-Based Redirects" is checked
```

**Example Configuration:**
```
# Admins and Editors
administrator|/wp-admin
editor|/editor-dashboard

# E-commerce roles
shop_manager|/shop-admin
customer|/my-account

# Membership roles
subscriber|/member-area
contributor|/contribute
```

**Format Rules:**
- One mapping per line
- Format: `role_name|/redirect-url`
- Role name must be valid WordPress role
- URL can be relative (`/page`) or absolute (`https://example.com/page`)
- Lines starting with `#` are ignored (comments)
- First matching role wins (if user has multiple roles)

---

#### 5. Auto-Login After Registration

```
Type: Checkbox
Default: Enabled
Description: Automatically log users in after successful registration
```

**Enabled:** User is logged in and redirected automatically  
**Disabled:** User sees success message but must log in manually

---

#### 6. Preserve ?redirect_to Parameter

```
Type: Checkbox
Default: Enabled
Description: Honor ?redirect_to query parameter from protected pages
```

**Use Case:** User tries to access protected page → redirected to login → logs in → returned to original page

**Example:**
```
User visits: /protected-content
Redirected to: /login?redirect_to=/protected-content
After login: /protected-content (preserved)
```

---

#### 7. Admins to Dashboard

```
Type: Checkbox
Default: Enabled
Description: Always redirect administrators to WordPress dashboard
```

When enabled, users with `manage_options` capability bypass all other redirect logic and go straight to `/wp-admin`.

**Security Note:** This prevents admins from being redirected to potentially untrusted URLs.

---

## Helper Methods

All redirect logic is handled by the `RedirectHelper` class.

### Get Login Redirect URL

```php
use WP_SMS\Services\OTP\Helpers\RedirectHelper;

// Get redirect for user after login
$user = get_user_by('id', $userId);
$shortcodeRedirect = '/custom-page'; // From shortcode attribute
$redirectTo = $_GET['redirect_to']; // From query parameter

$redirectUrl = RedirectHelper::getLoginRedirectUrl($user, $shortcodeRedirect, $redirectTo);
```

---

### Get Register Redirect URL

```php
use WP_SMS\Services\OTP\Helpers\RedirectHelper;

// Get redirect for user after registration
$user = get_user_by('id', $userId);
$shortcodeRedirect = '/welcome'; // From shortcode attribute

$redirectUrl = RedirectHelper::getRegisterRedirectUrl($user, $shortcodeRedirect);
```

---

### Get Global Redirects

```php
// Get global login redirect
$loginRedirect = RedirectHelper::getGlobalLoginRedirect();

// Get global register redirect
$registerRedirect = RedirectHelper::getGlobalRegisterRedirect();
```

---

### Role-Based Redirects

```php
// Check if role-based redirects are enabled
$enabled = RedirectHelper::isRoleBasedRedirectsEnabled();

// Get role redirect for a user
$user = get_user_by('id', $userId);
$roleRedirect = RedirectHelper::getRoleRedirectUrl($user);

// Get all role redirects
$redirects = RedirectHelper::getRoleRedirects();
// Returns: ['administrator' => '/wp-admin', 'editor' => '/editor-dashboard']
```

---

### Auto-Login Setting

```php
// Check if auto-login after registration is enabled
$autoLogin = RedirectHelper::isAutoLoginAfterRegisterEnabled();
```

---

### URL Sanitization

```php
// Sanitize and validate redirect URL
$safeUrl = RedirectHelper::sanitizeRedirectUrl('/my-page?param=value');
```

**Security Features:**
- Validates URL format
- Prevents open redirects (external URLs must be in allowed hosts)
- Converts relative URLs to absolute
- Escapes special characters

---

### Configuration Summary

```php
// Get complete redirect configuration
$config = RedirectHelper::getRedirectConfiguration();

/*
Returns:
[
    'login_redirect' => '/dashboard',
    'register_redirect' => '/welcome',
    'role_based_enabled' => true,
    'role_redirects' => [
        'administrator' => '/wp-admin',
        'subscriber' => '/my-account'
    ],
    'auto_login_after_register' => true,
    'preserve_redirect_to' => true,
    'admin_to_dashboard' => true
]
*/
```

---

### Validation

```php
// Validate role redirect configuration
$errors = RedirectHelper::validateRoleRedirects();

// Returns array of errors (empty if valid)
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $error . "\n";
    }
}
```

---

## Integration Examples

### Example 1: Login Endpoint Integration

```php
use WP_SMS\Services\OTP\Helpers\RedirectHelper;

class LoginVerifyAPIEndpoint {
    
    private function buildLoginSuccessResponse($user, string $flowId) {
        // Get redirect URL based on all configured rules
        $redirectTo = RedirectHelper::getRedirectToFromRequest();
        $redirectUrl = RedirectHelper::getLoginRedirectUrl($user, null, $redirectTo);
        
        return [
            'user_id' => $user->ID,
            'redirect_url' => $redirectUrl, // Include in response
            // ... other data
        ];
    }
}
```

---

### Example 2: Register Endpoint Integration

```php
use WP_SMS\Services\OTP\Helpers\RedirectHelper;

class RegisterVerifyAPIEndpoint {
    
    private function buildResponse($userId, $completionData) {
        $user = get_user_by('id', $userId);
        
        $data = [
            'user_id' => $userId,
            'registration_complete' => $completionData['status'] === 'complete',
        ];
        
        // Add redirect URL if registration is complete
        if ($data['registration_complete'] && $user) {
            $redirectUrl = RedirectHelper::getRegisterRedirectUrl($user);
            $data['redirect_url'] = $redirectUrl;
        }
        
        return $data;
    }
}
```

---

### Example 3: Frontend JavaScript Integration

```javascript
async function handleLoginSuccess(result) {
    // API provides redirect_url with role-based logic applied
    const redirectUrl = result.redirect_url || '/';
    
    // Show success message
    showMessage('Login successful! Redirecting...');
    
    // Redirect after delay
    setTimeout(() => {
        window.location.href = redirectUrl;
    }, 2000);
}
```

---

### Example 4: Shortcode with Custom Redirect

```php
// In page content
[wpsms_login_form redirect="/custom-dashboard"]

// Or programmatically
echo do_shortcode('[wpsms_login_form redirect="/my-page"]');
```

---

## Security

### Open Redirect Prevention

The system prevents **open redirect vulnerabilities** through:

1. **Host Validation**
   - Only same-host or subdomain redirects allowed by default
   - External URLs must be in `allowed_redirect_hosts` filter

2. **URL Sanitization**
   - All URLs passed through `esc_url_raw()`
   - Invalid URLs fallback to homepage

3. **Nonce Protection**
   - All redirects occur after successful authentication
   - Authentication itself protected by WordPress nonces

### Allowed External Hosts

To allow redirects to external domains:

```php
add_filter('allowed_redirect_hosts', function($hosts) {
    $hosts[] = 'trusted-partner.com';
    $hosts[] = 'app.example.com';
    return $hosts;
});
```

**Warning:** Only add truly trusted domains!

---

### Sanitization Example

```php
// Input: javascript:alert('xss')
// Output: / (homepage - malicious URL blocked)

// Input: https://evil.com/steal-tokens
// Output: / (homepage - external host not allowed)

// Input: /my-account?redirect=/admin
// Output: https://yoursite.com/my-account?redirect=/admin (sanitized)

// Input: //evil.com
// Output: / (homepage - protocol-relative external URL blocked)
```

---

## Troubleshooting

### Issue 1: User Not Redirected to Expected Page

**Check:**
1. Verify redirect settings are saved
2. Check user's role
3. Check redirect priority (shortcode > query param > role > global)
4. Check browser console for JavaScript errors

**Debug:**
```php
$user = get_user_by('id', $userId);
$redirect = RedirectHelper::getLoginRedirectUrl($user);
echo "Redirect URL: " . $redirect;
```

---

### Issue 2: Role-Based Redirect Not Working

**Check:**
1. Is "Enable Role-Based Redirects" checked?
2. Is role name spelled correctly?
3. Does user have that role?
4. Is URL format correct? (`role|/url`)

**Debug:**
```php
$redirects = RedirectHelper::getRoleRedirects();
print_r($redirects);

$user = get_user_by('id', $userId);
$roleRedirect = RedirectHelper::getRoleRedirectUrl($user);
echo "Role redirect: " . ($roleRedirect ?? 'none');
```

---

### Issue 3: External URL Not Allowed

**Solution:** Add to allowed hosts

```php
add_filter('allowed_redirect_hosts', function($hosts) {
    $hosts[] = 'external-site.com';
    return $hosts;
});
```

---

### Issue 4: ?redirect_to Not Working

**Check:**
1. Is "Preserve ?redirect_to Parameter" enabled?
2. Is this a login flow? (Not supported for register)
3. Is URL properly encoded in query string?

**Example:**
```
Correct: /login?redirect_to=%2Fprotected-page
Incorrect: /login?redirect=/protected-page (wrong param name)
```

---

## Changelog

### Version 1.0.0 (November 2025)

**Initial Release**

- ✅ Priority-based redirect system
- ✅ Global login and register redirect settings
- ✅ Role-based redirect configuration
- ✅ Shortcode attribute override support
- ✅ ?redirect_to parameter preservation
- ✅ Auto-login after registration toggle
- ✅ Admin to dashboard shortcut
- ✅ Open redirect prevention
- ✅ URL sanitization and validation
- ✅ Subdomain support
- ✅ RedirectHelper with comprehensive methods
- ✅ Integration with login/register API endpoints
- ✅ Frontend JavaScript redirect handling

**Components Added:**
- `OTPSettings.php` - Redirect Settings section
- `RedirectHelper.php` - Core redirect logic
- API endpoint updates (LoginVerifyAPIEndpoint, LoginMfaVerifyAPIEndpoint, RegisterVerifyAPIEndpoint)
- Frontend JavaScript redirect handling in `auth-form.js`

**Future Enhancements:**
- [ ] Time-based redirects (e.g., first login vs returning user)
- [ ] Conditional redirects based on custom user meta
- [ ] Redirect chains/sequences for onboarding
- [ ] A/B testing support for redirect strategies
- [ ] Redirect analytics and tracking

---

## Best Practices

### 1. Use Role-Based for Multi-Tenant Sites

```
administrator|/wp-admin
shop_manager|/shop-dashboard
customer|/my-account
subscriber|/member-area
```

**Why:** Different user types have different needs and permissions.

---

### 2. Keep Admins Separate

```
✓ Enable "Admins to Dashboard"
✗ Don't include administrator in role-based redirects
```

**Why:** Admins should always go to WP dashboard for security and consistency.

---

### 3. Use Shortcode Overrides for Special Pages

```php
// Login from pricing page → redirect to checkout
[wpsms_login_form redirect="/checkout"]

// Login from course page → redirect to course
[wpsms_login_form redirect="/courses/continue"]
```

**Why:** Context-aware redirects improve user experience.

---

### 4. Enable ?redirect_to for Protected Content

```
✓ Enable "Preserve ?redirect_to Parameter"
```

**Why:** Users expect to return to the page they were trying to access.

---

### 5. Auto-Login for Better UX

```
✓ Enable "Auto-Login After Registration"
```

**Why:** Reduces friction - user doesn't have to log in after just registering.

---

### 6. Validate Role Redirects

```php
$errors = RedirectHelper::validateRoleRedirects();
if (!empty($errors)) {
    // Fix configuration issues
}
```

**Why:** Prevents configuration errors from breaking user experience.

---

## Examples

### Example 1: E-commerce Site

**Settings:**
```
Default Login Redirect: /my-account
Default Register Redirect: /welcome
Enable Role-Based Redirects: ✓
Role Redirects:
  administrator|/wp-admin
  shop_manager|/shop-dashboard
  customer|/my-account
Auto-Login After Register: ✓
Admins to Dashboard: ✓
```

**User Flows:**
- Admin logs in → `/wp-admin`
- Shop Manager logs in → `/shop-dashboard`
- Customer logs in → `/my-account`
- New customer registers → auto-logged in → `/welcome`

---

### Example 2: Membership Site

**Settings:**
```
Default Login Redirect: /dashboard
Default Register Redirect: /onboarding
Enable Role-Based Redirects: ✓
Role Redirects:
  subscriber|/member-dashboard
  contributor|/content-submit
  author|/author-panel
Auto-Login After Register: ✓
Preserve redirect_to: ✓
```

**User Flows:**
- User tries to access `/premium-content` → redirected to login
- After login → `/premium-content` (preserved)
- New user registers → `/onboarding`

---

### Example 3: Multi-Site Network

**Settings:**
```
Default Login Redirect: /
Default Register Redirect: /
Enable Role-Based Redirects: ✓
Role Redirects:
  administrator|/wp-admin/network
  editor|/site-admin
  subscriber|/
Auto-Login After Register: ✓
Admins to Dashboard: ✓
```

---

### Example 4: Custom Shortcode Redirects

```html
<!-- Pricing page login -->
<a href="/login-pricing">[wpsms_login_form redirect="/checkout/premium"]</a>

<!-- Course enrollment login -->
<a href="/enroll">[wpsms_login_form redirect="/courses/start"]</a>

<!-- Job application -->
[wpsms_register_form redirect="/apply/step-2"]
```

---

## Integration Flow

### Server-Side (PHP)

1. User completes authentication
2. API endpoint calls `RedirectHelper::getLoginRedirectUrl($user)`
3. Helper applies priority logic (shortcode > query param > admin > role > global > fallback)
4. URL sanitized and validated
5. Included in API response as `redirect_url`

### Client-Side (JavaScript)

1. JavaScript receives API response
2. Extracts `result.redirect_url`
3. Shows success message
4. Waits 2 seconds
5. Redirects: `window.location.href = redirectUrl`

### Priority Resolution Example

```
User: editor (role)
Shortcode: redirect="/custom"
Query: ?redirect_to=/protected
Setting: administrator|/wp-admin, editor|/editor-area
Admin to Dashboard: ✓ (but user is not admin)

Result: /custom (shortcode wins)
```

---

## Security Considerations

### Redirect Injection Prevention

```php
// Bad input
$url = "javascript:alert('xss')";
$safe = RedirectHelper::sanitizeRedirectUrl($url);
// Result: / (blocked)

// Phishing attempt
$url = "https://evil-site.com/fake-login";
$safe = RedirectHelper::sanitizeRedirectUrl($url);
// Result: / (external host not allowed)
```

### XSS Prevention

All redirect URLs are:
- Validated with `parse_url()`
- Sanitized with `esc_url_raw()`
- Checked against allowed hosts

### CSRF Protection

- Redirects only occur after successful authentication
- Authentication protected by WordPress REST API nonces
- No redirects on failed auth attempts

---

## Changelog

### Version 1.0.0 (November 2025)

**Initial Release**

- ✅ Priority-based redirect system
- ✅ Global login/register redirects
- ✅ Role-based redirects with textarea configuration
- ✅ Shortcode attribute support
- ✅ ?redirect_to preservation
- ✅ Auto-login after registration
- ✅ Admin to dashboard shortcut
- ✅ URL sanitization and validation
- ✅ Open redirect prevention
- ✅ Subdomain support
- ✅ Complete documentation

---

**End of Documentation**

