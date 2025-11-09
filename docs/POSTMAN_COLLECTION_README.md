# WP-SMS OTP API - Postman Collection Guide

**Version:** 2.2.0  
**Collection File:** `WP-SMS_OTP_API.postman_collection.json`

---

## Quick Start

### 1. Import Collection

1. Open Postman
2. Click "Import" button
3. Select `WP-SMS_OTP_API.postman_collection.json`
4. Collection will appear in your workspace

---

### 2. Configure Variables

After importing, set these collection variables:

| Variable | Description | Example |
|----------|-------------|---------|
| `base_url` | Your WordPress REST API base | `https://yoursite.com/wp-json/wpsms/v1` |
| `wp_nonce` | WordPress REST API nonce | (see below) |

**Auto-Managed Variables** (set by scripts):
- `flow_id` - Auto-extracted from responses
- `mfa_flow_id` - Auto-extracted from MFA responses
- `reset_token` - Auto-extracted from password reset verify

---

### 3. Get WordPress Nonce

You need a valid WordPress REST API nonce. Here are three ways to get it:

#### Option A: Browser Console

1. Log into your WordPress site
2. Open browser DevTools (F12)
3. Go to Console tab
4. Run:
   ```javascript
   console.log(wpApiSettings.nonce);
   ```
5. Copy the nonce value
6. Paste into Postman's `wp_nonce` variable

#### Option B: PHP Script

Create a temporary page in WordPress:
```php
<?php
// Temporary - delete after getting nonce
wp_nonce_field('wp_rest', '_wpnonce', false);
echo wp_create_nonce('wp_rest');
?>
```

#### Option C: Postman Pre-request Script

Add this to a request's Pre-request Script:
```javascript
// First, make a request to your site to get cookies
pm.sendRequest({
    url: pm.collectionVariables.get('base_url').replace('/wp-json/wpsms/v1', '') + '/wp-admin',
    method: 'GET'
}, function (err, response) {
    // Nonce should now be in cookies
    // You'll need to handle WordPress authentication
});
```

---

## Collection Structure

### Folders

1. **Registration Flow** (5 requests)
   - Register Init
   - Register Start
   - Register Verify
   - Register Verify - Skip Optional
   - Register Add Identifier

2. **Login Flow** (5 requests)
   - Login Init
   - Login Start
   - Login Verify
   - Login MFA Challenge
   - Login MFA Verify

3. **Account Management** (5 requests)
   - Get Account Info
   - Update Email - Start
   - Update Email - Verify
   - Update Phone - Start
   - Update Phone - Verify

4. **MFA Management** (7 requests)
   - Get MFA Factors
   - Enable Email MFA - Start
   - Enable Email MFA - Verify
   - Disable Email MFA
   - Enable Phone MFA - Start
   - Enable Phone MFA - Verify
   - Disable Phone MFA

5. **Password Reset** (3 requests)
   - Reset Init
   - Reset Verify
   - Reset Complete

---

## Auto-Scripts

The collection includes automatic scripts that:

### Pre-request Script
- Auto-generates `flow_id` if not set

### Test Script (Post-response)
- Auto-extracts `flow_id` from responses
- Auto-extracts `mfa_flow_id` from MFA responses
- Auto-extracts `reset_token` from password reset
- Automatically sets variables for next request

**Example:**
```
1. POST /register/start
   Response: { "flow_id": "abc123..." }
   → Auto-sets {{flow_id}} = "abc123..."

2. POST /register/verify
   Uses: {{flow_id}} (already set!)
```

---

## Testing Flows

### Complete Registration Flow

1. **Register Init** (GET)
   - Returns channel settings
   - No variables needed

2. **Register Start** (POST)
   - Set: `identifier`, `auth_method`
   - Gets: `flow_id` (auto-saved)

3. **Register Verify** (POST)
   - Uses: `{{flow_id}}` (auto-filled)
   - Set: `code` or `token`

4. **Register Add Identifier** (POST) - If needed
   - Uses: `{{flow_id}}` (auto-filled)
   - Set: new `identifier`

5. **Repeat 2-3** until `registration_complete: true`

---

### Complete Login Flow (with MFA)

1. **Login Init** (GET)

2. **Login Start** (POST)
   - Set: `identifier`, `auth_method`
   - Gets: `flow_id`

3. **Login Verify** (POST)
   - Uses: `{{flow_id}}`
   - Set: `code`
   - If `mfa_required: true` → continue

4. **Login MFA Challenge** (POST)
   - Uses: `{{flow_id}}`
   - Set: `factor` (email/phone)
   - Gets: `mfa_flow_id`

5. **Login MFA Verify** (POST)
   - Uses: `{{mfa_flow_id}}`
   - Set: `code`
   - Complete!

---

### Complete Password Reset Flow

1. **Reset Init** (POST)
   - Set: `identifier`, `auth_method`
   - Gets: `flow_id`

2. **Reset Verify** (POST)
   - Uses: `{{flow_id}}`
   - Set: `code` or `token`
   - Gets: `reset_token`

3. **Reset Complete** (POST)
   - Uses: `{{reset_token}}`
   - Set: `new_password`, `confirm_password`
   - Done!

---

## Example Scenarios

### Scenario 1: Email OTP Registration

```
1. POST /register/start
   Body: {
     "identifier": "test@example.com",
     "auth_method": "otp"
   }

2. Check email for code

3. POST /register/verify
   Body: {
     "flow_id": "{{flow_id}}",  // Auto-filled
     "identifier": "test@example.com",
     "code": "123456"
   }

4. If registration_complete: true → Success!
```

---

### Scenario 2: Phone Magic Link Login

```
1. POST /login/start
   Body: {
     "identifier": "+1234567890",
     "auth_method": "magic"
   }

2. Check SMS for magic link

3. Extract token from link
   URL: https://site.com/magic?token=xyz789

4. POST /login/verify
   Body: {
     "flow_id": "{{flow_id}}",
     "identifier": "+1234567890",
     "token": "xyz789"
   }
```

---

### Scenario 3: Skip Optional Phone

```
1. Complete email verification first

2. POST /register/verify (skip action)
   Body: {
     "flow_id": "{{flow_id}}",
     "identifier_type": "phone",
     "action": "skip"
   }

3. Registration complete!
```

---

## Common Issues

### Issue: 401 Unauthorized

**Cause:** Invalid or expired nonce

**Solution:**
1. Regenerate nonce (see "Get WordPress Nonce")
2. Update `wp_nonce` variable
3. Retry request

---

### Issue: 429 Rate Limited

**Cause:** Too many requests

**Solution:**
1. Wait a few minutes
2. Or add your IP to whitelist (if you're admin)
3. Retry

---

### Issue: Variables Not Auto-Filling

**Cause:** Test script not running

**Solution:**
1. Check collection-level "Tests" tab
2. Ensure scripts are enabled in Postman settings
3. Check response format matches expected structure

---

## Advanced Usage

### Custom Environments

Create different environments for dev/staging/production:

**Development:**
```
base_url: http://localhost/wp-json/wpsms/v1
wp_nonce: dev_nonce_here
```

**Staging:**
```
base_url: https://staging.site.com/wp-json/wpsms/v1
wp_nonce: staging_nonce_here
```

**Production:**
```
base_url: https://site.com/wp-json/wpsms/v1
wp_nonce: prod_nonce_here
```

---

### Monitoring & Testing

Use Postman's Collection Runner to:
1. Run entire registration flow automatically
2. Test all endpoints sequentially
3. Generate test reports
4. Monitor API performance

---

### Example Pre-request Script

Add to specific requests for dynamic data:

```javascript
// Generate random email
const timestamp = Date.now();
pm.collectionVariables.set('test_email', `test${timestamp}@example.com`);

// Generate random phone
pm.collectionVariables.set('test_phone', `+1${Math.floor(Math.random() * 9000000000 + 1000000000)}`);
```

---

## Endpoints Quick Reference

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/register/init` | GET | No | Get registration config |
| `/register/start` | POST | No | Start registration |
| `/register/verify` | POST | No | Verify identifier |
| `/register/add-identifier` | POST | No | Add additional identifier |
| `/login/init` | GET | No | Get login config |
| `/login/start` | POST | No | Start login |
| `/login/verify` | POST | No | Verify primary auth |
| `/login/mfa-challenge` | POST | No | Send MFA challenge |
| `/login/mfa-verify` | POST | No | Verify MFA |
| `/account/me` | GET | Yes | Get account info |
| `/account/email/update` | POST | Yes | Update email |
| `/account/email/verify` | POST | Yes | Verify new email |
| `/account/phone/update` | POST | Yes | Update phone |
| `/account/phone/verify` | POST | Yes | Verify new phone |
| `/mfa/factors` | GET | Yes | Get MFA factors |
| `/mfa/email/enable` | POST | Yes | Enable email MFA |
| `/mfa/email/verify` | POST | Yes | Verify email MFA |
| `/mfa/email/disable` | POST | Yes | Disable email MFA |
| `/mfa/phone/enable` | POST | Yes | Enable phone MFA |
| `/mfa/phone/verify` | POST | Yes | Verify phone MFA |
| `/mfa/phone/disable` | POST | Yes | Disable phone MFA |
| `/password-reset/init` | POST | No | Start password reset |
| `/password-reset/verify` | POST | No | Verify reset code |
| `/password-reset/complete` | POST | No | Set new password |

**Auth:** "Yes" means requires authenticated user (logged in)

---

## Support

For issues with the collection:
1. Check variable configuration
2. Verify nonce is valid
3. Check WordPress site is accessible
4. Review endpoint documentation
5. Check WordPress debug logs

---

**End of Guide**

