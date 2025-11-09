# Password Reset Documentation

**Version:** 1.0.0  
**Last Updated:** November 2025

---

## Table of Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Architecture](#architecture)
4. [Configuration](#configuration)
5. [API Reference](#api-reference)
6. [Frontend Integration](#frontend-integration)
7. [Security Features](#security-features)
8. [Edge Cases](#edge-cases)
9. [Examples](#examples)
10. [Troubleshooting](#troubleshooting)
11. [Changelog](#changelog)

---

## Overview

The Password Reset system allows users to securely reset their password through email or SMS verification using OTP codes or Magic Links. It's conditionally available based on authentication configuration and seamlessly integrates with existing login/register flows.

### Key Features

- **Multi-Channel Support**: Reset via email or phone (based on verified identifiers)
- **Dual Verification Methods**: OTP codes or Magic Links
- **3-Step Secure Flow**: Init → Verify → Complete
- **Token Expiration**: Configurable expiry window (5-60 minutes)
- **Auto-Login Option**: Optionally log user in after successful reset
- **Rate Limiting**: Protection against brute force attacks
- **Event Logging**: Complete audit trail of reset attempts
- **Conditional Availability**: Auto-disabled for passwordless flows

---

## System Requirements

### When Password Reset is Available

Password reset is **only available** when:

1. ✅ Password authentication is enabled in at least one channel
2. ✅ At least one recovery identifier is configured (email or phone)
3. ✅ Password reset feature is enabled in settings

### Availability Matrix

| Configuration | Password Reset Available? |
|---------------|--------------------------|
| Username + Password + Email | ✅ Yes |
| Username + Password + Phone | ✅ Yes |
| Username + Password (no recovery) | ❌ No (see Edge Case) |
| Email + OTP (passwordless) | ❌ No |
| Phone + OTP (passwordless) | ❌ No |
| Email + Magic Link | ❌ No |

---

## Architecture

### Flow Diagram

```
┌─────────────────────────────────────────────────────────┐
│              Password Reset Flow                        │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  1. User enters email/phone                             │
│     ↓                                                    │
│  2. POST /password-reset/init                           │
│     → Find user                                          │
│     → Send OTP/Magic Link                                │
│     → Store flow_id in user meta                         │
│     ↓                                                    │
│  3. User enters code or clicks link                     │
│     ↓                                                    │
│  4. POST /password-reset/verify                         │
│     → Validate code/token                                │
│     → Generate reset_token                               │
│     → Store verification timestamp                       │
│     ↓                                                    │
│  5. User enters new password                            │
│     ↓                                                    │
│  6. POST /password-reset/complete                       │
│     → Validate reset_token                               │
│     → Check expiry                                       │
│     → Update password                                    │
│     → Invalidate sessions                                │
│     → Optional: Auto-login                               │
│     → Clean up reset data                                │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### Components

```
wp-content/plugins/wp-sms/src/
├── Settings/Groups/
│   └── OTPSettings.php                      # Password Reset section
├── Services/OTP/
│   ├── Helpers/
│   │   └── PasswordResetHelper.php         # Business logic
│   ├── RestAPIEndpoints/PasswordReset/
│   │   ├── PasswordResetInitAPIEndpoint.php     # POST /init
│   │   ├── PasswordResetVerifyAPIEndpoint.php   # POST /verify
│   │   └── PasswordResetCompleteAPIEndpoint.php # POST /complete
│   └── Shortcodes/
│       └── AuthShortcodes.php              # [wpsms_password_reset_form]
└── frontend/legacy/
    ├── js/
    │   └── auth-form.js                     # Password reset UI
    └── css/
        └── auth-form.css                    # Password reset styles
```

---

## Configuration

### Admin Settings

Navigate to: **WP-SMS → Settings → OTP Settings → Password Reset**

### Available Settings

#### 1. Enable Password Reset

```
Type: Checkbox
Default: Enabled
Description: Master switch for password reset feature
```

**When disabled:** "Forgot Password?" links are hidden, endpoints return 403 error.

---

#### 2. Reset Token Expiry (minutes)

```
Type: Number
Range: 5-60 minutes
Default: 15 minutes
Description: How long reset tokens/codes remain valid
```

**Security Note:** Shorter is more secure, but must allow time for email delivery.

**Recommendations:**
- Email delivery: 15-30 minutes
- SMS delivery: 5-10 minutes (faster)

---

#### 3. Auto-Login After Reset

```
Type: Checkbox
Default: Enabled
Description: Automatically log users in after successful password reset
```

**Enabled:** User is logged in and redirected automatically  
**Disabled:** User sees success message and must log in manually

---

#### 4. Allowed Recovery Identifiers

```
Type: Multi-select
Options: Email, Phone
Default: Both selected
Description: Which identifiers can be used to initiate password reset
```

**Best Practice:** Allow both for maximum flexibility.

---

#### 5. Require Identifier Verification

```
Type: Checkbox
Default: Enabled (Recommended)
Description: Only allow reset for identifiers verified during registration
```

**Enabled:** Uses IdentifierModel to verify ownership  
**Disabled:** Uses standard WordPress user lookup (less secure)

**Security Impact:**
- ✅ Enabled: Only verified identifiers (prevents account takeover)
- ⚠️ Disabled: Any identifier in WordPress database (higher risk)

---

#### 6. Minimum Password Length

```
Type: Number
Range: 6-32 characters
Default: 8 characters
Description: Minimum characters required for new password
```

**Security Recommendations:**
- Minimum 8 characters (industry standard)
- Consider 12+ for high-security applications

---

## API Reference

**Base URL:** `https://yoursite.com/wp-json/wpsms/v1/`

### 1. Initialize Password Reset

**Endpoint:** `POST /password-reset/init`

**Purpose:** Start password reset by sending verification code/link

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `identifier` | string | Yes | Email or phone number |
| `auth_method` | string | Yes | `otp` or `magic` |

**Example Request:**

```json
{
  "identifier": "user@example.com",
  "auth_method": "otp"
}
```

**Example Response:**

```json
{
  "success": true,
  "data": {
    "flow_id": "abc123...",
    "identifier_masked": "us**@example.com",
    "auth_method": "otp",
    "expires_in_minutes": 15
  },
  "message": "Verification code sent successfully"
}
```

**Security Note:** Always returns success even if user not found (prevents user enumeration).

---

### 2. Verify Code/Token

**Endpoint:** `POST /password-reset/verify`

**Purpose:** Verify OTP code or magic link token

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `flow_id` | string | Yes | Flow ID from init step |
| `code` | string | Conditional | OTP code (if using OTP) |
| `token` | string | Conditional | Magic link token (if using magic link) |

**Example Request:**

```json
{
  "flow_id": "abc123...",
  "code": "123456"
}
```

**Example Response:**

```json
{
  "success": true,
  "data": {
    "reset_token": "secure_token_here...",
    "user_id": 42,
    "message": "Verification successful. You can now reset your password"
  }
}
```

---

### 3. Complete Password Reset

**Endpoint:** `POST /password-reset/complete`

**Purpose:** Set new password and complete reset

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `reset_token` | string | Yes | Reset token from verify step |
| `new_password` | string | Yes | New password |
| `confirm_password` | string | Yes | Password confirmation (must match) |

**Example Request:**

```json
{
  "reset_token": "secure_token_here...",
  "new_password": "NewSecure123!",
  "confirm_password": "NewSecure123!"
}
```

**Example Response:**

```json
{
  "success": true,
  "data": {
    "user_id": 42,
    "auto_login": true,
    "auth_token": "session_token...",
    "redirect_url": "/my-account",
    "message": "Password reset successful"
  }
}
```

---

## Frontend Integration

### Shortcode Usage

#### Dedicated Password Reset Page

```php
[wpsms_password_reset_form]

// With custom redirect
[wpsms_password_reset_form redirect="/login"]

// With specific methods
[wpsms_password_reset_form methods="otp" redirect="/dashboard"]
```

---

#### Login Form with "Forgot Password?" Link

The login form automatically includes a "Forgot Password?" link when:
- Password reset is enabled
- Mode is `login` (not register or password_reset)

```php
[wpsms_login_form]
// Automatically shows "Forgot Password?" link at bottom
```

**Clicking the link:**
1. Switches form to password_reset mode
2. Shows reset flow UI
3. Includes "Back to Login" link

---

### JavaScript API

```javascript
// Access form instance
const authForm = new WPSMSAuthForm(container);

// Programmatically show password reset
authForm.handleShowPasswordReset();

// Programmatically return to login
authForm.handleBackToLogin();
```

---

## Security Features

### 1. Rate Limiting

All endpoints protected by rate limiting:
- `password_reset_init` - Prevents mass password reset requests
- `password_reset_verify` - Prevents brute force code guessing
- `password_reset_complete` - Prevents rapid password changes

**Bypassed for:** Whitelisted IPs (if configured)

---

### 2. Token Expiration

**Three expiry checkpoints:**

1. **Session Expiry** (`started_at`)
   - Checked in `/verify` endpoint
   - Based on `otp_password_reset_token_expiry` setting

2. **Verification Expiry** (`verified_at`)
   - Checked in `/complete` endpoint
   - Same expiry window as session

3. **OTP/Magic Link Expiry**
   - Handled by OtpService and MagicLinkService
   - Independent expiry timers

---

### 3. One-Time Use Tokens

**Reset Token Flow:**
```
/verify → generates reset_token
         → stores in user meta
         → single use only

/complete → validates reset_token
          → uses token
          → immediately deletes token + all reset meta
```

**Result:** Token cannot be reused even within expiry window.

---

### 4. Session Invalidation

After successful password reset:
```php
wp_set_password($newPassword, $user->ID);

// Destroy ALL existing sessions
$sessions = WP_Session_Tokens::get_instance($user->ID);
$sessions->destroy_all();
```

**Result:** User must log in on all devices (prevents hijacked session abuse).

---

### 5. User Enumeration Prevention

The `/init` endpoint **always returns success** even if:
- User doesn't exist
- Identifier not found
- Identifier not verified

**Generic Message:**
> "If an account exists with this identifier, you will receive a verification code"

**Why:** Prevents attackers from discovering valid user accounts.

---

### 6. Identifier Verification Requirement

When `otp_password_reset_require_verification` is enabled:
- Only identifiers verified during registration can be used
- Checks against `IdentifierModel`
- Prevents password reset via unverified contact info

---

### 7. Password Validation

**Server-Side Validation:**
```php
PasswordResetHelper::validatePassword($password)
// Checks:
// - Minimum length
// - (Optional) Complexity rules
```

**Client-Side Validation:**
```javascript
// HTML5 attributes
minlength="8"
required

// JavaScript matching
if (newPassword !== confirmPassword) {
    throw new Error('Passwords do not match');
}
```

---

## Edge Cases

### Edge Case 1: No Recovery Method Available

**Scenario:** Admin enables passwords but disables email AND phone collection.

**System Response:**
1. `PasswordResetHelper::isPasswordResetAvailable()` returns `false`
2. "Forgot Password?" link is hidden
3. API endpoints return 403 error
4. Admin sees warning in settings

**Warning Message:**
> "Password reset is enabled but no recovery identifiers are configured. Users will not be able to reset their passwords."

**Mitigation:** Admin should enable at least one of: email, phone

---

### Edge Case 2: Unverified Identifiers

**Scenario:** User has email in WordPress but never verified it via OTP system.

**With Verification Required (default):**
- User cannot reset password via that email
- Must use verified identifier only

**Without Verification Required:**
- User can reset via any identifier in WordPress
- Less secure but more flexible

---

### Edge Case 3: Expired Reset Session

**Scenario:** User starts reset but doesn't complete within expiry window.

**System Response:**
- `/verify` checks `started_at` timestamp
- If expired: returns error, cleans up session
- User must start over from `/init`

---

### Edge Case 4: Multiple Roles

**Scenario:** User has multiple roles (e.g., Editor + Shop Manager).

**Redirect Behavior:**
- First matching role in configuration wins
- If auto-login enabled, role-based redirect applies
- Follows same priority system as login

---

### Edge Case 5: Concurrent Reset Attempts

**Scenario:** User starts multiple reset flows simultaneously.

**System Response:**
- Each `/init` call overwrites previous flow_id
- Only most recent flow_id is valid
- Previous tokens become invalid

---

## Examples

### Example 1: Basic Password Reset

**User Flow:**
1. User goes to login page
2. Clicks "Forgot Password?"
3. Enters email: `user@example.com`
4. Receives OTP code: `123456`
5. Enters code
6. Enters new password
7. Auto-logged in and redirected to `/my-account`

**API Calls:**
```bash
# Step 1: Init
POST /password-reset/init
{
  "identifier": "user@example.com",
  "auth_method": "otp"
}

# Step 2: Verify
POST /password-reset/verify
{
  "flow_id": "abc123...",
  "code": "123456"
}

# Step 3: Complete
POST /password-reset/complete
{
  "reset_token": "xyz789...",
  "new_password": "NewSecure123!",
  "confirm_password": "NewSecure123!"
}
```

---

### Example 2: Magic Link Reset

**User Flow:**
1. Clicks "Forgot Password?"
2. Enters phone: `+1234567890`
3. Receives SMS with magic link
4. Clicks link → auto-verified
5. Enters new password
6. Redirected to homepage

**Configuration:**
```
Auth Method: magic
Identifier Type: phone
Auto-Login: true
Login Redirect: /
```

---

### Example 3: Dedicated Reset Page

**Create WordPress Page:**
```
Title: Reset Password
Content: [wpsms_password_reset_form redirect="/login"]
```

**User Experience:**
- Dedicated `/reset-password` URL
- After successful reset → redirected to `/login`
- Clean, focused UX

---

### Example 4: Role-Based Redirect After Reset

**Settings:**
```
Enable Role-Based Redirects: ✓
Role Redirects:
  administrator|/wp-admin
  customer|/my-account
Auto-Login After Reset: ✓
```

**User Flow:**
- Customer resets password
- Auto-logged in
- Redirected to `/my-account` (role-based)

---

## Troubleshooting

### Issue 1: "Password reset is not available"

**Causes:**
- Password reset disabled in settings
- No recovery identifiers configured
- Password authentication not enabled

**Debug:**
```php
$available = PasswordResetHelper::isPasswordResetAvailable();
$warnings = PasswordResetHelper::getConfigurationWarnings();
print_r($warnings);
```

---

### Issue 2: User Not Receiving Code

**Check:**
1. Is identifier verified? (if verification required)
2. Is identifier type allowed?
3. Email/SMS delivery configured correctly?
4. Check auth event logs for send failures

**Debug:**
```php
$user = PasswordResetHelper::findUserByIdentifier('user@example.com');
var_dump($user); // Should return WP_User

$methods = PasswordResetHelper::getAvailableRecoveryMethods($user->ID);
print_r($methods);
```

---

### Issue 3: "Invalid or expired reset token"

**Causes:**
- Token expired (check timestamps)
- Token already used
- Session cleaned up

**Debug:**
```php
$resetToken = get_user_meta($userId, 'wpsms_password_reset_token', true);
$verifiedAt = get_user_meta($userId, 'wpsms_password_reset_verified_at', true);

echo "Token: " . ($resetToken ? 'exists' : 'missing') . "\n";
echo "Verified: " . ($verifiedAt ? date('Y-m-d H:i:s', $verifiedAt) : 'never') . "\n";
echo "Expires at: " . date('Y-m-d H:i:s', $verifiedAt + (15 * 60)) . "\n";
```

---

### Issue 4: "Password does not meet requirements"

**Check:**
- Minimum password length setting
- Client-side validation (minlength attribute)
- Server-side validation

**Customize Validation:**
```php
// Add to PasswordResetHelper::validatePassword()
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = __('Must contain uppercase letter', 'wp-sms');
}
```

---

## Changelog

### Version 1.0.0 (November 2025)

**Initial Release**

- ✅ 3-step password reset flow (init, verify, complete)
- ✅ Multi-channel support (email, phone)
- ✅ Dual verification methods (OTP, Magic Link)
- ✅ Token expiration (configurable 5-60 minutes)
- ✅ Auto-login after reset (configurable)
- ✅ Identifier verification requirement
- ✅ Password strength validation
- ✅ Rate limiting on all endpoints
- ✅ Complete event logging
- ✅ User enumeration prevention
- ✅ Session invalidation after reset
- ✅ One-time use tokens
- ✅ Conditional availability (password-based auth only)
- ✅ Configuration health checks
- ✅ Shortcode support
- ✅ Frontend JavaScript integration
- ✅ Mobile-responsive UI
- ✅ Dark mode support
- ✅ RTL support

**Components Added:**
- `PasswordResetHelper.php`
- `PasswordResetInitAPIEndpoint.php`
- `PasswordResetVerifyAPIEndpoint.php`
- `PasswordResetCompleteAPIEndpoint.php`
- Shortcode: `[wpsms_password_reset_form]`
- "Forgot Password?" link in login forms
- "Back to Login" link in reset forms

**Future Enhancements:**
- [ ] Email template customization in admin
- [ ] SMS template customization in admin
- [ ] Password strength meter in UI
- [ ] Password history (prevent reuse)
- [ ] Configurable complexity requirements
- [ ] 2FA requirement for password reset
- [ ] Account lockout after failed attempts
- [ ] Notification emails (password changed alert)

---

**End of Documentation**

