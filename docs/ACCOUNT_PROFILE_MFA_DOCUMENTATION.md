# WP-SMS Account Profile & MFA Management

## Overview

Front-end account management system for logged-in users to manage their profile information and multi-factor authentication (MFA) factors.

---

## Features

### Profile Management
- Update personal information (first name, last name, display name)
- View username (read-only)
- Change email address (with verification)
- Change phone number (with verification)
- Verified badges for email/phone

### MFA Management
- View enrolled MFA factors
- Add Email OTP as MFA
- Add Phone OTP as MFA
- Remove MFA factors (with safety checks)
- TOTP (Coming Soon)
- Biometric/WebAuthn (Coming Soon)
- Backup Codes (Coming Soon)

---

## Shortcodes

### `[wpsms_account]`
Full account page with Profile and Security tabs

**Attributes**:
- `default_tab` - Default tab to show (`profile` or `security`, default: `profile`)
- `show_tabs` - Show tab navigation (`true` or `false`, default: `true`)

**Usage**:
```php
[wpsms_account]
[wpsms_account default_tab="security"]
```

### `[wpsms_account_profile]`
Profile tab only (no tabs)

**Usage**:
```php
[wpsms_account_profile]
```

### `[wpsms_account_mfa]`
Security/MFA tab only (no tabs)

**Usage**:
```php
[wpsms_account_mfa]
```

---

## REST API Endpoints

### Account Endpoints

#### `GET /account/me`
Get current user's profile and MFA summary

**Response**:
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "user": {
      "id": 123,
      "username": "johndoe",
      "first_name": "John",
      "last_name": "Doe",
      "display_name": "John D.",
      "email": {
        "value_masked": "jo***@example.com",
        "verified": true,
        "verified_at": "2024-01-15 10:30:00"
      },
      "phone": {
        "value_masked": "+12***890",
        "verified": true,
        "verified_at": "2024-01-15 10:35:00"
      },
      "locale": "en_US",
      "avatar_url": "https://..."
    },
    "mfa_summary": {
      "enrolled": ["email", "phone"],
      "allowed": ["email", "phone"],
      "total_factors": 2
    }
  }
}
```

#### `POST /account/me`
Update profile information

**Parameters**:
- `first_name` (string, optional)
- `last_name` (string, optional)
- `display_name` (string, optional)
- `locale` (string, optional)

**Response**: Updated profile data (same as GET)

#### `POST /account/email`
Start email address change

**Parameters**:
- `email` (string, required) - New email address

**Response**:
```json
{
  "success": true,
  "message": "Verification code sent to new email address",
  "data": {
    "flow_id": "email_change_abc123",
    "email_masked": "ne***@example.com",
    "next_step": "verify",
    "otp_ttl_seconds": 300
  }
}
```

#### `POST /account/email/verify`
Verify email address change

**Parameters**:
- `flow_id` (string, required)
- `code` (string, required) - 6-digit OTP code

**Response**:
```json
{
  "success": true,
  "message": "Email address updated successfully",
  "data": {
    "email": "newemail@example.com"
  }
}
```

#### `POST /account/phone`
Start phone number change

**Parameters**:
- `phone` (string, required) - New phone number with country code

**Response**:
```json
{
  "success": true,
  "message": "Verification code sent to new phone number",
  "data": {
    "flow_id": "phone_change_abc123",
    "phone_masked": "+12***890",
    "next_step": "verify",
    "otp_ttl_seconds": 300
  }
}
```

#### `POST /account/phone/verify`
Verify phone number change

**Parameters**:
- `flow_id` (string, required)
- `code` (string, required) - 6-digit OTP code

**Response**:
```json
{
  "success": true,
  "message": "Phone number updated successfully",
  "data": {
    "phone": "+1234567890"
  }
}
```

---

### MFA Endpoints

#### `GET /mfa/factors`
Get all enrolled MFA factors for current user

**Response**:
```json
{
  "success": true,
  "message": "MFA factors retrieved successfully",
  "data": {
    "factors": [
      {
        "id": 1,
        "type": "email",
        "label": "Email OTP (jo***@example.com)",
        "value_masked": "jo***@example.com",
        "verified": true,
        "verified_at": "2024-01-15 10:30:00",
        "last_used_at": "2024-01-20 14:30:00",
        "created_at": "2024-01-15 10:30:00"
      },
      {
        "id": 2,
        "type": "phone",
        "label": "Phone OTP (+12***890)",
        "value_masked": "+12***890",
        "verified": true,
        "verified_at": "2024-01-15 10:35:00",
        "last_used_at": null,
        "created_at": "2024-01-15 10:35:00"
      }
    ],
    "allowed_types": ["email", "phone"],
    "total_enrolled": 2,
    "policy": {
      "min_factors": 0,
      "allowed_types": ["email", "phone"]
    }
  }
}
```

#### `POST /mfa/email/add`
Start email MFA enrollment

**Parameters**:
- `email` (string, required)

**Response**:
```json
{
  "success": true,
  "message": "Verification code sent to email",
  "data": {
    "flow_id": "mfa_email_abc123",
    "email_masked": "mf***@example.com",
    "next_step": "verify",
    "otp_ttl_seconds": 300
  }
}
```

#### `POST /mfa/email/verify`
Verify and activate email MFA

**Parameters**:
- `flow_id` (string, required)
- `code` (string, required)

**Response**:
```json
{
  "success": true,
  "message": "Email MFA enrolled successfully",
  "data": {
    "factor_id": 3,
    "type": "email"
  }
}
```

#### `DELETE /mfa/email/{id}`
Remove email MFA factor

**Response**:
```json
{
  "success": true,
  "message": "Email MFA removed successfully",
  "data": {
    "factor_id": 3
  }
}
```

#### `POST /mfa/phone/add`
Start phone MFA enrollment

**Parameters**:
- `phone` (string, required)

**Response**: Same structure as email/add

#### `POST /mfa/phone/verify`
Verify and activate phone MFA

**Parameters**:
- `flow_id` (string, required)
- `code` (string, required)

**Response**: Same structure as email/verify

#### `DELETE /mfa/phone/{id}`
Remove phone MFA factor

**Response**: Same structure as email delete

---

## User Flows

### Change Email Address

```
User clicks "Change Email"
    ↓
Modal opens
    ↓
User enters new email
    ↓
POST /account/email
    ↓
Code sent to new email
    ↓
User enters code
    ↓
POST /account/email/verify
    ↓
Email updated in wp_users & identifiers table
    ↓
Success toast + page reload
```

### Enroll Email MFA

```
User clicks "Add" on Email MFA card
    ↓
Modal opens
    ↓
User enters email address
    ↓
POST /mfa/email/add
    ↓
Code sent to email
    ↓
User enters code
    ↓
POST /mfa/email/verify
    ↓
New identifier record created with factor_type='email'
    ↓
Success toast + MFA list reloaded
```

### Remove MFA Factor

```
User clicks "Remove" on factor card
    ↓
Confirmation dialog
    ↓
User confirms
    ↓
DELETE /mfa/{type}/{id}
    ↓
Safety check: prevent removing last factor
    ↓
Factor deleted from identifiers table
    ↓
Success toast + MFA list reloaded
```

---

## Security & Validation

### Email Change
- ✅ Email format validation
- ✅ Uniqueness check (wp_users + identifiers table)
- ✅ OTP verification required
- ✅ Rate limiting
- ✅ Event logging
- ✅ Old email deactivated, new email activated

### Phone Change
- ✅ E.164 format validation
- ✅ Uniqueness check (identifiers table)
- ✅ OTP verification required
- ✅ Rate limiting
- ✅ Event logging
- ✅ Updates both identifiers table and user meta

### MFA Enrollment
- ✅ Check if MFA channel is enabled
- ✅ Prevent duplicate enrollment
- ✅ OTP verification required
- ✅ Rate limiting
- ✅ Event logging

### MFA Removal
- ✅ Ownership verification
- ✅ Safety check: cannot remove last factor
- ✅ Event logging
- ✅ Confirmation required (client-side)

---

## Event Logging

### New Event Types

| Event Type | When | Channel | Result |
|------------|------|---------|--------|
| `profile_update` | Profile fields updated | `system` | `allow` |
| `identifier_update_start` | Email/phone change started | `email`/`phone` | `allow` |
| `identifier_verify` | Email/phone verified | `email`/`phone` | `allow`/`deny` |
| `mfa_enroll_start` | MFA enrollment started | `email`/`phone` | `allow` |
| `mfa_enroll_verify` | MFA enrollment verified | `email`/`phone` | `allow`/`deny` |
| `mfa_remove` | MFA factor removed | `email`/`phone` | `allow` |

---

## Frontend Architecture

### Vanilla JavaScript Classes

**`ProfileManager`**:
- Loads profile data from API
- Handles profile form submission
- Manages email/phone change modals

**`EmailChangeManager`**:
- Handles email change flow
- Sends verification code
- Verifies and updates email

**`PhoneChangeManager`**:
- Handles phone change flow
- Sends verification code
- Verifies and updates phone

**`MfaManager`**:
- Loads MFA factors from API
- Renders factor cards
- Updates status banner
- Handles factor removal

**`EmailMfaManager`**:
- Handles email MFA enrollment
- Sends verification code
- Verifies and activates factor

**`PhoneMfaManager`**:
- Handles phone MFA enrollment
- Sends verification code
- Verifies and activates factor

### Helper Functions

- `apiRequest(endpoint, options)` - REST API wrapper
- `showToast(message, type)` - Toast notifications
- `showModal(id)` / `hideModal(id)` - Modal management

---

## Styling

### CSS Variables

All colors, spacing, and styling use CSS variables for easy theming:

```css
:root {
    --wpsms-color-primary: #3b82f6;
    --wpsms-color-success: #10b981;
    --wpsms-color-danger: #ef4444;
    --wpsms-spacing-md: 1rem;
    --wpsms-radius-md: 0.375rem;
    /* ... more variables */
}
```

### Dark Mode

Automatically supports dark mode via `[data-theme="dark"]`:

```css
[data-theme="dark"] {
    --wpsms-color-bg: #1f2937;
    --wpsms-color-text: #f9fafb;
    /* ... */
}
```

### RTL Support

Full RTL support for Arabic, Hebrew, etc.:

```css
[dir="rtl"] .wpsms-account-tabs {
    direction: rtl;
}
```

---

## Accessibility (WCAG 2.1 AA)

### Features

- ✅ Semantic HTML
- ✅ ARIA labels and roles
- ✅ Keyboard navigation
- ✅ Focus management
- ✅ Screen reader support
- ✅ Color contrast 4.5:1 minimum
- ✅ Focus indicators
- ✅ Error announcements via `aria-live`

### Keyboard Support

- `Tab` - Navigate between fields/buttons
- `Enter` - Submit forms
- `Escape` - Close modals
- `Arrow keys` - Navigate tabs

---

## Integration Guide

### Adding to a Page

1. Create a WordPress page
2. Add shortcode: `[wpsms_account]`
3. Publish

### Customizing Styles

Override CSS variables in your theme:

```css
.wpsms-account-container {
    --wpsms-color-primary: #your-brand-color;
    --wpsms-radius-md: 0.5rem;
}
```

### Custom Templates

Copy template to your theme:

```
your-theme/wpsms/account-page.php
your-theme/wpsms/account-profile.php
your-theme/wpsms/account-mfa.php
```

---

## Error Handling

### Error Codes

| Code | Message | HTTP Status |
|------|---------|-------------|
| `email_in_use` | Email already in use | 409 |
| `phone_in_use` | Phone already in use | 409 |
| `invalid_code` | Invalid verification code | 400 |
| `session_expired` | Session expired | 400 |
| `rate_limited` | Too many requests | 429 |
| `mfa_disabled` | MFA not enabled | 403 |
| `already_enrolled` | Factor already enrolled | 409 |
| `last_factor` | Cannot remove last factor | 400 |
| `send_failed` | Failed to send code | 500 |

### Client-Side Handling

All errors are displayed via toast notifications:

```javascript
try {
    await apiRequest('account/email', { ... });
} catch (error) {
    showToast(error.message, 'error');
}
```

---

## Database Changes

### Identifiers Table

When user changes email/phone or enrolls MFA:

**Email Change**:
1. Old email identifier: Updated with new value
2. `wp_users.user_email`: Updated
3. `verified_at`: Set to current timestamp

**Phone Change**:
1. Old phone identifier: Updated with new value
2. `wpsms_phone` user meta: Updated
3. `verified_at`: Set to current timestamp

**MFA Enrollment**:
1. New identifier record created
2. `factor_type`: 'email' or 'phone'
3. `verified`: true
4. `verified_at`: Current timestamp

---

## User Meta Keys

### Temporary (During Verification)

| Key | Purpose | Cleanup |
|-----|---------|---------|
| `wpsms_email_change_flow_id` | Email change session | After verification |
| `wpsms_email_change_new` | New email (pending) | After verification |
| `wpsms_phone_change_flow_id` | Phone change session | After verification |
| `wpsms_phone_change_new` | New phone (pending) | After verification |
| `wpsms_mfa_email_flow_id` | Email MFA enrollment | After verification |
| `wpsms_mfa_email_new` | Email for MFA (pending) | After verification |
| `wpsms_mfa_phone_flow_id` | Phone MFA enrollment | After verification |
| `wpsms_mfa_phone_new` | Phone for MFA (pending) | After verification |

### Permanent

| Key | Purpose |
|-----|---------|
| `wpsms_phone` | User's verified phone number |
| `locale` | User's preferred locale |
| `first_name` | User's first name |
| `last_name` | User's last name |

---

## File Structure

```
wp-content/plugins/wp-sms/
├── src/Services/OTP/
│   ├── RestAPIEndpoints/
│   │   ├── Account/
│   │   │   ├── AccountMeAPIEndpoint.php
│   │   │   ├── AccountEmailAPIEndpoint.php
│   │   │   └── AccountPhoneAPIEndpoint.php
│   │   └── MFA/
│   │       ├── MfaFactorsAPIEndpoint.php
│   │       ├── MfaEmailAPIEndpoint.php
│   │       └── MfaPhoneAPIEndpoint.php
│   ├── Shortcodes/
│   │   └── AccountShortcodes.php
│   └── Templates/
│       ├── account-page.php
│       ├── account-profile.php
│       └── account-mfa.php
├── assets/
│   ├── js/
│   │   └── account.js
│   └── css/
│       └── account.css
```

---

## Testing Checklist

### Profile Tests

- [ ] Load profile data
- [ ] Update first/last/display name
- [ ] Change email (happy path)
- [ ] Change email (email in use - error)
- [ ] Change email (invalid code - error)
- [ ] Change phone (happy path)
- [ ] Change phone (phone in use - error)
- [ ] Verify badges show correctly

### MFA Tests

- [ ] Load MFA factors
- [ ] Enroll email MFA (happy path)
- [ ] Enroll email MFA (already enrolled - error)
- [ ] Enroll phone MFA (happy path)
- [ ] Remove email MFA (happy path)
- [ ] Remove phone MFA (happy path)
- [ ] Cannot remove last factor
- [ ] Status banner updates correctly

### Security Tests

- [ ] Rate limiting works
- [ ] Session expiration works
- [ ] Invalid codes rejected
- [ ] Ownership verification works
- [ ] Events logged correctly

### Accessibility Tests

- [ ] Keyboard navigation
- [ ] Screen reader announcements
- [ ] Focus management
- [ ] Color contrast
- [ ] ARIA labels

### RTL Tests

- [ ] Layout mirrors correctly
- [ ] Text alignment correct
- [ ] Icons positioned correctly

---

## Coming Soon

### TOTP (Authenticator Apps)
- QR code generation
- Secret key display
- TOTP validation
- Time sync handling

### Biometric/WebAuthn
- Device registration
- Challenge/response flow
- Credential management
- Platform authenticator support

### Backup Codes
- Code generation (8-10 codes)
- Download as .txt
- Print functionality
- One-time use enforcement

### Trusted Devices
- Device fingerprinting
- Trust for 30 days
- Revoke device access
- Device naming

---

## Support

For issues or questions, refer to:
- `OTP_SERVICE_DOCUMENTATION.md` - Main technical docs
- `AUTH_EVENTS_DOCUMENTATION.md` - Event logging reference
- `IMPLEMENTATION_SUMMARY.md` - Implementation checklist

---

## Changelog

### Version 1.0.0
- Initial release
- Profile management with email/phone verification
- Email OTP MFA enrollment
- Phone OTP MFA enrollment
- Factor removal with safety checks
- Vanilla JS implementation
- RTL and dark mode support
- WCAG 2.1 AA compliant

---

## License

Part of the WP-SMS plugin by VeronaLabs.

