# WP-SMS Authentication Events - Complete Reference

## Table of Contents
1. [Overview](#overview)
2. [Event Types](#event-types)
3. [Event Data Structure](#event-data-structure)
4. [Results & Outcomes](#results--outcomes)
5. [Channels](#channels)
6. [Device Types](#device-types)
7. [Event Lifecycle](#event-lifecycle)
8. [Querying Events](#querying-events)
9. [Analytics & Reporting](#analytics--reporting)
10. [Best Practices](#best-practices)

---

## Overview

The WP-SMS OTP Service logs comprehensive authentication events for:
- **Security monitoring**: Track failed attempts and suspicious activity
- **Analytics**: Understand user behavior and conversion rates
- **Debugging**: Troubleshoot authentication issues
- **Compliance**: Maintain audit trails for regulatory requirements
- **Optimization**: Identify bottlenecks and improve flows

All events are stored in the `sms_auth_events` table with automatic:
- UUID generation
- Timestamp tracking
- Device detection
- IP masking
- Configurable retention

---

## Event Types

### Registration Events

#### `register_init`
**Description**: User initiates registration with an identifier

**Triggered by**: `POST /register/start`

**When**: 
- User provides email or phone to start registration
- OTP/Magic Link is generated and sent

**Common channels**: `email`, `sms`, `phone`

**Result**: `allow` (success) or `deny` (rate limited/error)

**Example**:
```json
{
  "event_type": "register_init",
  "flow_id": "flow_67890abc123",
  "channel": "email",
  "result": "allow",
  "user_id": null,
  "client_ip_masked": "192.168.1.1"
}
```

---

#### `register_verify`
**Description**: User verifies their identifier with OTP code or magic link

**Triggered by**: `POST /register/verify` with `action=verify`

**When**:
- User submits OTP code
- User clicks magic link
- Identifier is marked as verified

**Common channels**: `otp`, `magic`, `email`, `sms`

**Result**: `allow` (valid code/link) or `deny` (invalid/expired)

**Example**:
```json
{
  "event_type": "register_verify",
  "flow_id": "flow_67890abc123",
  "channel": "otp",
  "result": "allow",
  "user_id": 123,
  "attempt_count": 1
}
```

---

#### `register_skip`
**Description**: User skips an optional identifier

**Triggered by**: `POST /register/verify` with `action=skip`

**When**:
- User chooses to skip an optional (non-required) identifier
- System marks the identifier type as skipped

**Common channels**: `email`, `phone` (identifier type)

**Result**: `allow` (skip successful)

**Example**:
```json
{
  "event_type": "register_skip",
  "flow_id": "flow_67890abc123",
  "channel": "phone",
  "result": "allow",
  "user_id": 123
}
```

---

#### `register_add_identifier`
**Description**: User adds an additional identifier during registration

**Triggered by**: `POST /register/add-identifier`

**When**:
- Multiple identifiers are required (e.g., email + phone)
- User adds second/third identifier
- New OTP/Magic Link generated with new flow ID

**Common channels**: `email`, `sms`, `phone`

**Result**: `allow` (identifier added) or `deny` (validation failed)

**Example**:
```json
{
  "event_type": "register_add_identifier",
  "flow_id": "flow_new123abc",
  "channel": "sms",
  "result": "allow",
  "user_id": 123
}
```

---

### Login Events

#### `login_init`
**Description**: User initiates login with an identifier

**Triggered by**: `POST /login/start`

**When**: 
- User provides identifier (email, phone, or username) to start login
- OTP/Magic Link is generated and sent

**Common channels**: `email`, `sms`, `phone`

**Result**: `allow` (success) or `deny` (rate limited/user not found)

**Example**:
```json
{
  "event_type": "login_init",
  "flow_id": "flow_login123",
  "channel": "email",
  "result": "allow",
  "user_id": 123,
  "client_ip_masked": "192.168.1.1"
}
```

---

#### `login_verify`
**Description**: User verifies primary authentication

**Triggered by**: `POST /login/verify`

**When**:
- User submits OTP code, magic link, or password
- Primary authentication is verified

**Common channels**: `otp`, `magic`, `password`

**Result**: `allow` (valid) or `deny` (invalid/expired)

**Example**:
```json
{
  "event_type": "login_verify",
  "flow_id": "flow_login123",
  "channel": "otp",
  "result": "allow",
  "user_id": 123,
  "attempt_count": 1
}
```

---

#### `mfa_challenge_sent`
**Description**: MFA challenge sent to user

**Triggered by**: `POST /login/mfa-challenge`

**When**:
- Primary authentication successful
- MFA is required
- MFA challenge (OTP/Magic Link/TOTP) generated and sent

**Common channels**: `email`, `sms`, `phone`, `totp`

**Result**: `allow` (sent successfully)

**Example**:
```json
{
  "event_type": "mfa_challenge_sent",
  "flow_id": "mfa_abc123",
  "channel": "phone",
  "result": "allow",
  "user_id": 123
}
```

---

#### `mfa_challenge_verify`
**Description**: User verifies MFA challenge

**Triggered by**: `POST /login/mfa-verify`

**When**:
- User submits MFA code/token
- MFA verification attempted

**Common channels**: `otp`, `magic`, `totp`, `webauthn`

**Result**: `allow` (valid) or `deny` (invalid/expired)

**Example**:
```json
{
  "event_type": "mfa_challenge_verify",
  "flow_id": "mfa_abc123",
  "channel": "otp",
  "result": "allow",
  "user_id": 123,
  "attempt_count": 1
}
```

---

#### `login_success`
**Description**: Successful login completed

**Triggered by**: After all authentication factors verified

**When**: 
- Primary auth verified
- MFA verified (if required)
- Auth token generated

**Common channels**: `system`

**Result**: `allow`

**Example**:
```json
{
  "event_type": "login_success",
  "flow_id": "flow_login123",
  "channel": "system",
  "result": "allow",
  "user_id": 123
}
```

---

#### `login_failed`
**Description**: Login attempt failed

**Triggered by**: Invalid credentials or verification failure

**When**: Authentication fails at any stage

**Common channels**: Varies based on failure point

**Result**: `deny`

**Example**:
```json
{
  "event_type": "login_failed",
  "flow_id": "flow_login123",
  "channel": "otp",
  "result": "deny",
  "user_id": 123,
  "attempt_count": 3
}
```

---

### Password Reset Events (Coming Soon)

#### `password_reset_request`
**Description**: User requests password reset

#### `password_reset_verify`
**Description**: User verifies password reset

#### `password_reset_complete`
**Description**: Password successfully reset

---

### 2FA/MFA Events (Coming Soon)

#### `mfa_enroll_init`
**Description**: User starts MFA enrollment

#### `mfa_enroll_verify`
**Description**: User verifies MFA factor

#### `mfa_challenge_sent`
**Description**: MFA challenge sent to user

#### `mfa_challenge_verify`
**Description**: User verifies MFA challenge

---

## Event Data Structure

### Core Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `id` | BIGINT | Auto-increment primary key | `12345` |
| `event_id` | CHAR(36) | UUID for event | `550e8400-e29b-41d4-a716-446655440000` |
| `flow_id` | CHAR(36) | Unique flow identifier | `flow_67890abc123` |
| `timestamp_utc` | TIMESTAMP | Event timestamp (UTC) | `2024-01-15 10:30:00` |
| `user_id` | BIGINT | WordPress user ID (nullable) | `123` |
| `channel` | VARCHAR(64) | Delivery/auth channel | `email`, `sms`, `otp`, `magic` |
| `event_type` | VARCHAR(64) | Event type | `register_init`, `register_verify` |
| `result` | VARCHAR(32) | Event outcome | `allow`, `deny`, `n/a` |

### Context Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `client_ip_masked` | VARCHAR(64) | Masked client IP | `192.168.1.1` |
| `geo_country` | CHAR(2) | Country code (ISO 3166-1) | `US`, `GB`, `CA` |
| `wp_role` | VARCHAR(32) | WordPress user role | `subscriber`, `administrator` |
| `device_type` | VARCHAR(32) | Detected device type | `mobile`, `desktop`, `tablet`, `bot` |
| `user_agent` | TEXT | Full user agent string | `Mozilla/5.0 ...` |

### Delivery Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `vendor_sid` | VARCHAR(64) | Vendor message ID | `msg_abc123`, `SM123456` |
| `vendor_status` | VARCHAR(32) | Delivery status | `sent`, `delivered`, `failed` |
| `attempt_count` | SMALLINT | Number of attempts | `1`, `2`, `3` |

### Metadata Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `factor_id` | CHAR(36) | MFA factor ID (if applicable) | `factor_123` |
| `retention_days` | SMALLINT | How long to retain event | `30`, `90`, `365` |

---

## Results & Outcomes

### Result Types

| Result | Meaning | Use Case |
|--------|---------|----------|
| `allow` | Operation succeeded | Verification successful, identifier added |
| `deny` | Operation failed | Invalid code, rate limited, expired token |
| `n/a` | Not applicable | Informational events, non-binary outcomes |

### Common Deny Reasons

1. **Rate Limited**: Too many attempts in time window
2. **Invalid Code**: OTP/token doesn't match
3. **Expired**: Token/session expired
4. **Already Used**: One-time token already consumed
5. **Not Found**: Flow/user not found
6. **Validation Failed**: Format or business rule violation

---

## Channels

### Authentication Channels

| Channel | Description | Used In |
|---------|-------------|---------|
| `otp` | OTP code verification | verify events |
| `magic` | Magic link verification | verify events |
| `password` | Password authentication | login events |
| `totp` | Time-based OTP | 2FA events |
| `webauthn` | WebAuthn/FIDO2 | 2FA events |
| `backup` | Backup codes | 2FA events |

### Delivery Channels

| Channel | Description | Used In |
|---------|-------------|---------|
| `email` | Email delivery | init, add-identifier events |
| `sms` | SMS delivery | init, add-identifier events |
| `phone` | Phone (generic) | init, add-identifier events |
| `whatsapp` | WhatsApp delivery | Future implementation |
| `call` | Voice call | Future implementation |

---

## Device Types

Automatically detected from User Agent:

| Type | Detection Pattern | Example User Agents |
|------|-------------------|---------------------|
| `mobile` | Contains "mobile" but not "tablet" | iPhone, Android mobile |
| `tablet` | Contains "tablet" | iPad, Android tablet |
| `desktop` | Default for non-mobile/tablet | Windows, macOS browsers |
| `bot` | Contains "bot" or "crawl" | Googlebot, search crawlers |
| `unknown` | No user agent provided | API clients, curl |

---

## Event Lifecycle

### Registration Flow Events

```
┌─────────────────────────────────────────────────────────────┐
│                    Registration Flow                         │
└─────────────────────────────────────────────────────────────┘

Step 1: User provides email
        ↓
  ┌──────────────────┐
  │ register_init    │  result: allow
  │ channel: email   │  OTP/Magic Link sent
  └──────────────────┘
        ↓
Step 2: User enters OTP code
        ↓
  ┌──────────────────┐
  │ register_verify  │  result: allow
  │ channel: otp     │  Email verified
  └──────────────────┘
        ↓
Step 3: System asks for phone (if required)
        ↓
  ┌──────────────────┐
  │ register_add_    │  result: allow
  │   identifier     │  OTP sent to phone
  │ channel: sms     │
  └──────────────────┘
        ↓
Step 4: User enters OTP code
        ↓
  ┌──────────────────┐
  │ register_verify  │  result: allow
  │ channel: otp     │  Phone verified
  └──────────────────┘
        ↓
    User Activated ✓
```

### Optional Identifier Skip Flow

```
Step 1: Email verified
        ↓
Step 2: System offers optional phone
        ↓
  User chooses to skip
        ↓
  ┌──────────────────┐
  │ register_skip    │  result: allow
  │ channel: phone   │  Phone skipped
  └──────────────────┘
        ↓
    User Activated ✓
```

### Login Flow Events

```
┌─────────────────────────────────────────────────────────────┐
│                      Login Flow (with MFA)                   │
└─────────────────────────────────────────────────────────────┘

Step 1: User provides email
        ↓
  ┌──────────────────┐
  │ login_init       │  result: allow
  │ channel: email   │  OTP sent to email
  └──────────────────┘
        ↓
Step 2: User enters OTP code
        ↓
  ┌──────────────────┐
  │ login_verify     │  result: allow
  │ channel: otp     │  Primary auth verified
  └──────────────────┘
        ↓
Step 3: System checks MFA requirement
        ↓
  MFA Required ✓
        ↓
Step 4: User selects MFA method (phone)
        ↓
  ┌──────────────────┐
  │ mfa_challenge_   │  result: allow
  │   sent           │  OTP sent to phone
  │ channel: phone   │
  └──────────────────┘
        ↓
Step 5: User enters MFA OTP code
        ↓
  ┌──────────────────┐
  │ mfa_challenge_   │  result: allow
  │   verify         │  MFA verified
  │ channel: otp     │
  └──────────────────┘
        ↓
  ┌──────────────────┐
  │ login_success    │  result: allow
  │ channel: system  │  Auth token issued
  └──────────────────┘
        ↓
    Login Complete ✓
```

### Login Flow (No MFA)

```
Step 1: login_init (email) → allow
Step 2: login_verify (otp) → allow
Step 3: login_success (system) → allow
        ↓
    Login Complete ✓
```

---

## Querying Events

### Using AuthEventModel

#### Get Events by Flow ID
```php
$eventModel = new AuthEventModel();
$events = $eventModel->getByFlow('flow_67890abc123');
```

#### Get Recent Events for User
```php
$eventModel = new AuthEventModel();
$events = $eventModel->getRecentForUser(123, 10);
```

#### Direct Insert
```php
AuthEventModel::log([
    'flow_id' => 'flow_123',
    'event_type' => 'register_init',
    'result' => 'allow',
    'channel' => 'email',
    'user_id' => 123,
]);
```

### Custom Queries

```php
$events = AuthEventModel::findAll([
    'event_type' => 'register_verify',
    'result' => 'deny',
], 50, 'timestamp_utc DESC');
```

---

## Analytics & Reporting

### Available Widgets

#### Health Snapshot
- Total events
- Success rate
- Average response time
- Error rate

#### Journey Funnels
- Registration funnel conversion
- Drop-off points
- Completion rates

#### Volume Over Time
- Events per hour/day/week
- Trend analysis
- Peak usage times

#### Method Mix
- Distribution of auth methods
- Channel preference
- Device breakdown

#### Delivery Quality
- Success vs failure rates
- Vendor performance
- Channel reliability

#### Geographic Distribution
- Events by country
- Regional patterns
- Heatmap visualization

### Filters Available

**Time & Flow**:
- Date range (Last 1h, 24h, 7d, 30d, custom)
- Flow type (Login, Registration, Both)
- Scenario (specific use cases)

**User & Role**:
- User ID (comma-separated)
- WordPress role (multi-select)

**Channel & Method**:
- Auth channel (SMS, Email, WhatsApp, Social)
- 2FA method (SMS OTP, Email OTP, TOTP, Push)

**Outcome**:
- Event type (Requested, Sent, Verified, Failed)
- Result (Allow, Deny, N/A)
- Vendor status (Delivered, Soft-fail, Hard-fail, Empty)

**Geo & Network**:
- Country (searchable)
- Client IP (partial match)

**Security**:
- Minimum attempt count

**Operations**:
- Vendor SID lookup

**Global**:
- Quick search (flow_id, event_id, vendor_sid, IP)

---

## Event Flow Examples

### Example 1: Successful Registration (Email Only)

```json
[
  {
    "event_id": "550e8400-e29b-41d4-a716-446655440001",
    "flow_id": "flow_67890abc123",
    "timestamp_utc": "2024-01-15 10:30:00",
    "event_type": "register_init",
    "channel": "email",
    "result": "allow",
    "user_id": null,
    "device_type": "desktop",
    "client_ip_masked": "192.168.1.1"
  },
  {
    "event_id": "550e8400-e29b-41d4-a716-446655440002",
    "flow_id": "flow_67890abc123",
    "timestamp_utc": "2024-01-15 10:32:15",
    "event_type": "register_verify",
    "channel": "otp",
    "result": "allow",
    "user_id": 123,
    "attempt_count": 1,
    "device_type": "desktop",
    "client_ip_masked": "192.168.1.1"
  }
]
```

### Example 2: Multi-Identifier Registration (Email + Phone)

```json
[
  {
    "event_type": "register_init",
    "flow_id": "flow_67890abc123",
    "channel": "email",
    "result": "allow"
  },
  {
    "event_type": "register_verify",
    "flow_id": "flow_67890abc123",
    "channel": "otp",
    "result": "allow"
  },
  {
    "event_type": "register_add_identifier",
    "flow_id": "flow_new123def",
    "channel": "sms",
    "result": "allow"
  },
  {
    "event_type": "register_verify",
    "flow_id": "flow_new123def",
    "channel": "otp",
    "result": "allow"
  }
]
```

### Example 3: Registration with Optional Skip

```json
[
  {
    "event_type": "register_init",
    "flow_id": "flow_67890abc123",
    "channel": "email",
    "result": "allow"
  },
  {
    "event_type": "register_verify",
    "flow_id": "flow_67890abc123",
    "channel": "magic",
    "result": "allow"
  },
  {
    "event_type": "register_skip",
    "flow_id": "flow_67890abc123",
    "channel": "phone",
    "result": "allow",
    "note": "User skipped optional phone verification"
  }
]
```

### Example 4: Failed Verification (Rate Limited)

```json
[
  {
    "event_type": "register_init",
    "flow_id": "flow_67890abc123",
    "channel": "email",
    "result": "allow"
  },
  {
    "event_type": "register_verify",
    "flow_id": "flow_67890abc123",
    "channel": "otp",
    "result": "deny",
    "attempt_count": 1,
    "note": "Invalid OTP code"
  },
  {
    "event_type": "register_verify",
    "flow_id": "flow_67890abc123",
    "channel": "otp",
    "result": "deny",
    "attempt_count": 2
  },
  {
    "event_type": "register_verify",
    "flow_id": "flow_67890abc123",
    "channel": "otp",
    "result": "deny",
    "attempt_count": 15,
    "note": "Rate limited"
  }
]
```

---

## Best Practices

### 1. Event Logging

**Always log**:
- All authentication attempts (success and failure)
- Rate limit violations
- Security-relevant events
- User activation

**Include context**:
```php
$this->logAuthEvent(
    $flowId, 
    'register_verify', 
    'allow', 
    'otp', 
    $ip,
    1, // attempt count
    [
        'user_id' => $userId,
        'vendor_sid' => 'msg_123',
    ]
);
```

### 2. Flow Correlation

Use consistent `flow_id` throughout a user journey:
- Registration: Same flow_id until identifier verified
- New identifier: Generate new flow_id
- Login: One flow_id per login attempt

### 3. Result Classification

| Scenario | Result |
|----------|--------|
| Success | `allow` |
| Business rule failure | `deny` |
| Rate limited | `deny` |
| System error | Log separately, don't insert event |
| Informational | `n/a` |

### 4. Channel Selection

Use the most specific channel:
- `otp` instead of `sms` when verifying OTP
- `magic` instead of `email` when clicking magic link
- `email`/`sms` when sending artifacts

### 5. Retention Policy

Set retention based on compliance needs:
```php
AuthEventModel::log([
    'flow_id' => $flowId,
    'event_type' => 'register_verify',
    'result' => 'allow',
    'retention_days' => 90, // Keep for 90 days
]);
```

**Recommended retention**:
- Security events: 90-365 days
- Registration events: 30-90 days
- Login events: 30-60 days
- Analytics: 7-30 days

---

## Security Monitoring

### Suspicious Activity Detection

Monitor for:
1. **High failure rates**: Multiple `deny` results from same IP
2. **Rapid attempts**: Many events in short time window
3. **Geographic anomalies**: Login from unusual country
4. **Device changes**: Different device_type for same user
5. **Brute force**: High attempt_count values

### Query Examples

**Failed verifications in last hour**:
```php
$failed = AuthEventModel::findAll([
    'event_type' => 'register_verify',
    'result' => 'deny',
], 100, 'timestamp_utc DESC');

// Filter by time
$oneHourAgo = gmdate('Y-m-d H:i:s', time() - 3600);
$recent = array_filter($failed, function($event) use ($oneHourAgo) {
    return $event['timestamp_utc'] >= $oneHourAgo;
});
```

**High attempt counts (potential brute force)**:
```php
// Via SQL or advanced query builder
// WHERE attempt_count >= 5 AND result = 'deny'
```

---

## Privacy & Compliance

### IP Masking

Client IPs are stored as provided but can be masked:
```php
// Full IP: 192.168.1.100
// Masked IP: 192.168.1.xxx (last octet hidden)
```

### Data Minimization

Only log what's necessary:
- Don't log sensitive credentials
- Don't log full tokens (only hashes if needed)
- Use vendor_sid instead of full message content

### GDPR Compliance

Events can be:
- **Exported**: For user data requests
- **Deleted**: Based on retention_days
- **Anonymized**: Remove user_id, keep aggregates

### Retention Management

Automatic cleanup via cron:
```php
// Delete events older than retention period
AuthEventModel::deleteWhere([
    'timestamp_utc' => ['<', gmdate('Y-m-d H:i:s', time() - (retention_days * 86400))]
]);
```

---

## Integration Examples

### Custom Event Logging

```php
use WP_SMS\Services\OTP\Models\AuthEventModel;

// Log custom authentication event
AuthEventModel::log([
    'event_id' => wp_generate_uuid4(),
    'flow_id' => $flowId,
    'event_type' => 'custom_auth_event',
    'result' => 'allow',
    'channel' => 'custom',
    'user_id' => $userId,
    'client_ip_masked' => $ip,
    'retention_days' => 30,
]);
```

### Hooks for Event Tracking

```php
// After identifier verified
add_action('wpsms_identifier_verified', function($user, $identifier, $identifierType) {
    AuthEventModel::log([
        'flow_id' => UserHelper::getUserFlowId($user->ID),
        'event_type' => 'identifier_verified',
        'result' => 'n/a',
        'channel' => $identifierType,
        'user_id' => $user->ID,
    ]);
});

// After user activated
add_action('wpsms_user_activated', function($user) {
    AuthEventModel::log([
        'flow_id' => UserHelper::getUserFlowId($user->ID),
        'event_type' => 'user_activated',
        'result' => 'allow',
        'channel' => 'system',
        'user_id' => $user->ID,
    ]);
});
```

---

## Database Schema

```sql
CREATE TABLE `sms_auth_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` CHAR(36) NOT NULL,
  `flow_id` CHAR(36) NOT NULL,
  `timestamp_utc` TIMESTAMP NOT NULL,
  `user_id` BIGINT NULL,
  `channel` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `result` VARCHAR(32) NOT NULL,
  `client_ip_masked` VARCHAR(64) NULL,
  `geo_country` CHAR(2) NULL,
  `wp_role` VARCHAR(32) NULL,
  `vendor_sid` VARCHAR(64) NULL,
  `vendor_status` VARCHAR(32) NULL,
  `factor_id` CHAR(36) NULL,
  `attempt_count` SMALLINT NULL,
  `retention_days` SMALLINT NOT NULL DEFAULT 30,
  `user_agent` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_id` (`event_id`),
  KEY `idx_auth_flow` (`flow_id`),
  KEY `idx_auth_user_ts` (`user_id`, `timestamp_utc` DESC),
  KEY `idx_auth_factor` (`factor_id`)
);
```

### Indexes Explained

| Index | Purpose |
|-------|---------|
| `PRIMARY KEY (id)` | Unique row identifier |
| `UNIQUE KEY (event_id)` | Prevent duplicate events |
| `KEY (flow_id)` | Fast flow-based queries |
| `KEY (user_id, timestamp_utc DESC)` | User event history |
| `KEY (factor_id)` | MFA factor tracking |

---

## Performance Considerations

### Query Optimization

1. **Use indexes**: Always filter by indexed columns
2. **Limit results**: Use pagination for large datasets
3. **Date ranges**: Always specify time bounds
4. **Avoid wildcards**: At start of LIKE patterns

### Batch Operations

```php
// Good: Query once, process in memory
$events = AuthEventModel::findAll(['flow_id' => $flowId]);
foreach ($events as $event) {
    // Process
}

// Bad: N+1 queries
foreach ($flowIds as $flowId) {
    $event = AuthEventModel::find(['flow_id' => $flowId]);
}
```

### Retention Cleanup

Schedule cleanup job:
```php
// Via WP-Cron
add_action('wpsms_daily_cleanup', function() {
    // Delete events past retention period
    global $wpdb;
    $table = $wpdb->prefix . 'sms_auth_events';
    $wpdb->query(
        "DELETE FROM {$table} 
         WHERE DATEDIFF(NOW(), timestamp_utc) > retention_days"
    );
});
```

---

## Event Type Reference

### Current Implementation

| Event Type | Trigger | Common Channels | Common Results |
|------------|---------|-----------------|----------------|
| `register_init` | `/register/start` | `email`, `sms`, `phone` | `allow`, `deny` |
| `register_verify` | `/register/verify` (verify) | `otp`, `magic` | `allow`, `deny` |
| `register_skip` | `/register/verify` (skip) | `email`, `phone` | `allow` |
| `register_add_identifier` | `/register/add-identifier` | `email`, `sms`, `phone` | `allow`, `deny` |
| `login_init` | `/login/start` | `email`, `sms`, `phone` | `allow`, `deny` |
| `login_verify` | `/login/verify` | `otp`, `magic`, `password` | `allow`, `deny` |
| `mfa_challenge_sent` | `/login/mfa-challenge` | `email`, `sms`, `phone`, `totp` | `allow` |
| `mfa_challenge_verify` | `/login/mfa-verify` | `otp`, `magic`, `totp`, `webauthn` | `allow`, `deny` |
| `login_success` | After verification | `system` | `allow` |

### Future Events (Coming Soon)

| Event Type | Trigger | Purpose |
|------------|---------|---------|
| `password_reset_request` | Password reset | Request reset link |
| `password_reset_verify` | Reset verification | Verify reset token |
| `password_reset_complete` | Password updated | Reset completed |
| `mfa_enroll_init` | MFA enrollment | Start MFA setup |
| `mfa_enroll_verify` | MFA enrollment | Verify MFA factor |

---

## Troubleshooting

### Common Issues

**1. Events not appearing in logs**
- Check if AuthEventModel::log() throws exception
- Verify database table exists
- Check retention period not expired

**2. Incorrect timestamps**
- Events use UTC timestamps
- Convert to local timezone for display

**3. Missing user_id**
- Normal for `register_init` (user not created yet)
- Should be present for `register_verify` and later

**4. Duplicate event_id**
- UUID collision (extremely rare)
- Check for retry logic causing duplicates

### Debug Mode

Enable logging to troubleshoot:
```php
add_action('wpsms_before_log_event', function($data) {
    error_log('Auth Event: ' . print_r($data, true));
});
```

---

## Changelog

### Version 2.1.0
- Added login event types: `login_init`, `login_verify`, `login_success`
- Added MFA event types: `mfa_challenge_sent`, `mfa_challenge_verify`
- Added complete login flow documentation
- Added login flow diagrams

### Version 2.0.0
- Added `register_skip` event type
- Added skip tracking for optional identifiers
- Enhanced documentation

### Version 1.0.0
- Initial event logging system
- Registration event types
- Device detection
- Analytics support

---

## License

This event logging system is part of the WP-SMS plugin and follows the same license terms.

---

## Contributors

Created by VeronaLabs for the WP-SMS plugin ecosystem.

