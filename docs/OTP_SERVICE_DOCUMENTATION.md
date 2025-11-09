# WP-SMS OTP Service - Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Features](#features)
4. [Technical Implementation](#technical-implementation)
5. [API Reference](#api-reference)
6. [Database Schema](#database-schema)
7. [Security Features](#security-features)
8. [Administration](#administration)
9. [Code Patterns & Best Practices](#code-patterns--best-practices)
10. [Integration Guide](#integration-guide)

---

## Overview

The WP-SMS OTP Service is a comprehensive authentication and verification system that provides:

- **Multi-Factor Authentication (MFA)** via OTP codes and Magic Links
- **Multi-Channel Support** (Email, SMS, WhatsApp, etc.)
- **Registration Flow** with pending user management
- **Rate Limiting** and security controls
- **Comprehensive Logging** and analytics
- **Flexible Configuration** per channel

### Key Concepts

- **Flow ID**: A unique identifier for each authentication/registration session
- **Pending User**: A user account in intermediate state awaiting verification
- **Identifier**: A verified communication method (email or phone) for a user
- **Authentication Channel**: Method used to verify (OTP, Magic Link, Password)
- **Delivery Channel**: Method used to deliver (Email, SMS, WhatsApp)

---

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     OTPManager (Main Service)                │
│                                                              │
│  ┌───────────────────┐  ┌────────────────────────────────┐ │
│  │   REST API Layer  │  │    Authentication Channels     │ │
│  │                   │  │                                │ │
│  │  • /register/init │  │  • OTP Service                 │ │
│  │  • /register/start│  │  • MagicLink Service           │ │
│  │  • /register/     │  │  • Combined Channel            │ │
│  │    verify         │  │                                │ │
│  │  • /register/     │  └────────────────────────────────┘ │
│  │    add-identifier │                                     │
│  └───────────────────┘  ┌────────────────────────────────┐ │
│                          │     Delivery Channels           │ │
│  ┌───────────────────┐  │                                │ │
│  │   Models Layer    │  │  • EmailChannel                │ │
│  │                   │  │  • SmsChannel                  │ │
│  │  • OtpSessionModel│  └────────────────────────────────┘ │
│  │  • MagicLinkModel │                                     │
│  │  • IdentifierModel│  ┌────────────────────────────────┐ │
│  │  • AuthEventModel │  │      Security & Helpers        │ │
│  └───────────────────┘  │                                │ │
│                          │  • RateLimiter                 │ │
│  ┌───────────────────┐  │  • UserHelper                  │ │
│  │   Helper Layer    │  │  • ChannelSettingsHelper       │ │
│  │                   │  └────────────────────────────────┘ │
│  │  • UserHelper     │                                     │
│  │  • ChannelSettings│  ┌────────────────────────────────┐ │
│  │  • Response       │  │     Admin & Frontend           │ │
│  └───────────────────┘  │                                │ │
│                          │  • OTPAdminPage                │ │
│  ┌───────────────────┐  │  • ActivityOverviewReportPage  │ │
│  │   Admin Layer     │  │  • Shortcodes & Assets         │ │
│  │                   │  └────────────────────────────────┘ │
│  │  • OTPAdminPage   │                                     │
│  │  • Reports        │                                     │
│  │  • Logs           │                                     │
│  └───────────────────┘                                     │
└─────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
wp-content/plugins/wp-sms/src/Services/OTP/
├── Admin/
│   └── Pages/
│       └── OTPAdminPage.php              # Admin interface
├── Assets/
│   └── AuthAssets.php                    # Frontend assets
├── AuthChannel/
│   ├── AuthChannelManager.php
│   ├── OTP/
│   │   ├── OtpService.php               # OTP generation & validation
│   │   └── OtpPayload.php
│   ├── MagicLink/
│   │   ├── MagicLinkService.php         # Magic link generation & validation
│   │   └── MagicLinkPayload.php
│   └── OTPMagicLink/
│       └── OTPMagicLinkCombinedChannel.php  # Combined auth channel
├── Contracts/
│   ├── Interfaces/
│   │   ├── AuthChannelInterface.php
│   │   └── DeliveryChannelInterface.php
│   └── Abstracts/
├── Delivery/
│   ├── DeliveryChannelManager.php
│   ├── Email/
│   │   ├── EmailChannel.php
│   │   └── Templating/
│   │       ├── EmailTemplate.php
│   │       ├── EmailTemplateRegistry.php
│   │       ├── EmailTemplateStorage.php
│   │       ├── SanitizeCallbacks.php
│   │       └── TemplateRenderer.php
│   └── PhoneNumber/
│       ├── SmsChannel.php
│       └── Templating/
│           ├── SmsTemplate.php
│           ├── SmsTemplateRegistry.php
│           ├── SmsTemplateStorage.php
│           ├── SanitizeCallbacks.php
│           └── TemplateRenderer.php
├── Helpers/
│   ├── ChannelSettingsHelper.php        # Channel configuration
│   ├── Response.php
│   ├── UserHelper.php                   # User operations
│   ├── UsernameHelper.php
├── Hooks/
│   └── AuthHooks.php
├── Models/
│   ├── AuthEventModel.php               # Event logging
│   ├── IdentifierModel.php              # Verified identifiers
│   ├── MagicLinkModel.php               # Magic link storage
│   └── OtpSessionModel.php              # OTP session storage
├── RestAPIEndpoints/
│   ├── Abstracts/
│   │   └── RestAPIEndpointsAbstract.php # Base API class
│   └── Register/
│       ├── RegisterInitApiEndpoints.php      # Initialize flow
│       ├── RegisterStartAPIEndpoint.php      # Start verification
│       ├── RegisterVerifyAPIEndpoint.php     # Verify code/link
│       └── RegisterAddIdentifierAPIEndpoint.php  # Add identifier
├── Security/
│   └── RateLimiter.php                  # Rate limiting
├── Shortcodes/
│   └── AuthShortcodes.php
├── Templates/
│   └── AuthTemplates.php
├── OTPChannelHelper.php
└── OTPManager.php                       # Main service entry point
```

---

## Features

### 1. Registration Flow

The registration flow supports multi-step verification with multiple identifiers:

#### Flow: `/register/start`
- **Purpose**: Initialize registration with an identifier
- **Process**:
  1. Validate identifier format
  2. Check rate limits
  3. Check identifier availability
  4. Create or retrieve pending user
  5. Load channel settings
  6. Generate OTP and/or Magic Link based on configuration
  7. Send authentication artifacts via appropriate channel
  8. Log event and return response

**Response**:
```json
{
  "success": true,
  "message": "Registration initiated successfully",
  "data": {
    "flow_id": "flow_67890abc123",
    "user_id": 123,
    "identifier_type": "email",
    "identifier_masked": "us***@example.com",
    "next_step": "verify",
    "otp_enabled": true,
    "magic_link_enabled": true,
    "combined_enabled": false,
    "channel_used": "email",
    "otp_ttl_seconds": 300
  }
}
```

#### Flow: `/register/verify`
- **Purpose**: Verify a code or magic link
- **Process**:
  1. Validate flow ID
  2. Check rate limits
  3. Resolve pending user
  4. Determine verification method (OTP or Magic Link)
  5. Validate code/token
  6. Mark identifier as verified
  7. Check if all required identifiers are verified
  8. Activate user if complete
  9. Log event and return response

**Response**:
```json
{
  "success": true,
  "message": "Registration completed successfully",
  "data": {
    "user_id": 123,
    "flow_id": "flow_67890abc123",
    "status": "verified",
    "next_step": "complete",
    "next_required_identifier": null,
    "verified_identifiers": {
      "email": {
        "identifier": "user@example.com",
        "verified_at": "2024-01-15 10:30:00"
      }
    },
    "verified_via": "otp"
  }
}
```

#### Flow: `/register/add-identifier`
- **Purpose**: Add additional identifier during registration
- **Use Case**: When multiple identifiers are required (e.g., both email and phone)
- **Process**:
  1. Validate new identifier
  2. Check if type is required
  3. Ensure identifier not already verified
  4. Verify availability
  5. Generate new flow ID and persist identifier
  6. Create and send authentication artifacts
  7. Return next steps

**Response**:
```json
{
  "success": true,
  "message": "Identifier added successfully. Please verify.",
  "data": {
    "user_id": 123,
    "flow_id": "flow_new123abc",
    "identifier": "+1234567890",
    "identifier_type": "phone",
    "identifier_masked": "+12***890",
    "channel_used": "sms",
    "verified_identifiers": {
      "email": {
        "identifier": "user@example.com",
        "verified_at": "2024-01-15 10:30:00"
      }
    },
    "next_required_identifier": null,
    "next_step": "verify_current",
    "otp_enabled": true,
    "magic_link_enabled": false,
    "combined_enabled": false,
    "otp_ttl_seconds": 300
  }
}
```

### 2. Login Flow

The login flow supports passwordless authentication with optional MFA:

#### Flow: `/login/start`
- **Purpose**: Initialize login with an identifier
- **Process**:
  1. Validate identifier (email, phone, or username)
  2. Check rate limits
  3. Find user by identifier
  4. Check if user is active (not pending)
  5. Load channel settings
  6. Generate OTP and/or Magic Link based on configuration
  7. Send authentication artifacts
  8. Log event and return response

**Response**:
```json
{
  "success": true,
  "message": "Login initiated successfully",
  "data": {
    "flow_id": "flow_login123",
    "identifier_type": "email",
    "identifier_masked": "us***@example.com",
    "next_step": "verify",
    "otp_enabled": true,
    "magic_link_enabled": true,
    "password_enabled": true,
    "combined_enabled": false,
    "channel_used": "email",
    "mfa_required": true,
    "otp_ttl_seconds": 300
  }
}
```

#### Flow: `/login/verify`
- **Purpose**: Verify primary authentication
- **Process**:
  1. Validate flow ID
  2. Check rate limits
  3. Get user by flow ID
  4. Verify with OTP, Magic Link, or Password
  5. Check if MFA is required
  6. Log event and return response

**Response (MFA Required)**:
```json
{
  "success": true,
  "message": "MFA verification required",
  "data": {
    "flow_id": "flow_login123",
    "next_step": "mfa_challenge",
    "mfa_required": true,
    "mfa_options": [
      {
        "type": "phone",
        "masked": "+12***890",
        "methods": ["otp", "magic"]
      },
      {
        "type": "totp",
        "methods": ["totp"]
      }
    ],
    "message": "Primary authentication successful. MFA verification required."
  }
}
```

**Response (No MFA)**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user_id": 123,
    "user_login": "johndoe",
    "user_email": "user@example.com",
    "display_name": "John Doe",
    "auth_token": "abc123...token",
    "next_step": "complete"
  }
}
```

#### Flow: `/login/mfa-challenge`
- **Purpose**: Send MFA challenge to user-selected method
- **Process**:
  1. Validate flow ID
  2. Check user has selected MFA method enrolled
  3. Load MFA channel settings
  4. Generate MFA challenge (OTP/Magic Link/TOTP)
  5. Send challenge via appropriate channel
  6. Log event and return response

**Response**:
```json
{
  "success": true,
  "message": "MFA challenge sent successfully",
  "data": {
    "mfa_flow_id": "mfa_abc123",
    "mfa_method": "phone",
    "mfa_identifier_masked": "+12***890",
    "next_step": "mfa_verify",
    "otp_enabled": true,
    "magic_link_enabled": false,
    "combined_enabled": false,
    "otp_ttl_seconds": 300
  }
}
```

#### Flow: `/login/mfa-verify`
- **Purpose**: Verify MFA challenge
- **Process**:
  1. Validate MFA flow ID and original flow ID
  2. Check rate limits
  3. Verify MFA code/token
  4. Complete login and generate auth token
  5. Clean up flow IDs
  6. Log success events

**Response**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user_id": 123,
    "user_login": "johndoe",
    "user_email": "user@example.com",
    "display_name": "John Doe",
    "auth_token": "abc123...token",
    "next_step": "complete"
  }
}
```

### 3. Authentication Channels

#### OTP Service
- **Location**: `AuthChannel/OTP/OtpService.php`
- **Features**:
  - Secure OTP generation (4-10 digits, configurable)
  - SHA-256 hashed storage
  - Configurable TTL (default 300s)
  - Automatic expiration
  - Session reuse detection
  - One-time use validation

**Usage**:
```php
$otpService = new OtpService();

// Generate OTP
$session = $otpService->generate('flow_123', 'user@example.com', 6);

// Send OTP
$result = $otpService->sendOTP(
    'user@example.com', 
    $session->code, 
    'email'
);

// Validate OTP
$isValid = $otpService->validate('flow_123', '123456');
```

#### Magic Link Service
- **Location**: `AuthChannel/MagicLink/MagicLinkService.php`
- **Features**:
  - Secure token generation (32-char hex)
  - Configurable TTL (default 600s)
  - One-time use enforcement
  - Automatic expiration
  - URL generation

**Usage**:
```php
$magicService = new MagicLinkService();

// Generate Magic Link
$magicLink = $magicService->generate('flow_123', 'user@example.com', 'email');

// Send Magic Link
$result = $magicService->sendMagicLink(
    'user@example.com', 
    $magicLink
);

// Validate Magic Link
$isValid = $magicService->validate('flow_123', 'token_xyz');
```

#### Combined Channel
- **Location**: `AuthChannel/OTPMagicLink/OTPMagicLinkCombinedChannel.php`
- **Features**:
  - Simultaneous OTP + Magic Link generation
  - Single message delivery
  - Unified validation
  - Template support

**Usage**:
```php
$combined = new OTPMagicLinkCombinedChannel();

// Generate both
$result = $combined->generate('flow_123', 'user@example.com', 'email', 6);

// Send combined message
$sendResult = $combined->sendCombined(
    'user@example.com',
    $result['otp_session'],
    $result['magic_link'],
    'register'
);
```

### 3. Delivery Channels

#### Email Channel
- **Location**: `Delivery/Email/EmailChannel.php`
- **Features**:
  - HTML and plain text support
  - Template-based rendering
  - Automatic header management
  - Delivery logging

#### SMS Channel
- **Location**: `Delivery/PhoneNumber/SmsChannel.php`
- **Features**:
  - Template-based rendering
  - Character limit awareness
  - Delivery logging
  - Integration with WP-SMS core

**Delivery Channel Manager**:
```php
$manager = new DeliveryChannelManager();

// Get channel
$emailChannel = $manager->get('email');
$smsChannel = $manager->get('sms');

// Send via channel
$emailChannel->send('user@example.com', '', [
    'template' => EmailTemplate::TYPE_OTP_CODE,
    'otp_code' => '123456'
]);
```

### 4. Rate Limiting

- **Location**: `Security/RateLimiter.php`
- **Implementation**:
  - WordPress transient-based
  - Per-identifier and per-IP limiting
  - Configurable TTL and limits
  - Automatic expiration

**Default Limits**:
- Max attempts: 15 per window
- Window: 300 seconds (5 minutes)

**Usage**:
```php
$limiter = new RateLimiter();

// Check if allowed
if (!$limiter->isAllowed('identifier:user@example.com', 5, 300)) {
    throw new Exception('Rate limit exceeded');
}

// Increment counter
$limiter->increment('identifier:user@example.com', 300);
```

### 5. Pending User Management

- **Location**: `Helpers/UserHelper.php`
- **Features**:
  - Automatic username generation
  - Flow ID assignment
  - Identifier tracking
  - Verified identifier storage
  - Activation workflow
  - Cleanup utilities

**User States**:
1. **Pending**: User created, identifiers being verified
2. **Active**: All required identifiers verified, user activated

**Usage**:
```php
// Create pending user
$user = UserHelper::createPendingUser('user@example.com', [
    'flow_id' => 'flow_123'
]);

// Check if pending
$isPending = UserHelper::isPendingUser($user->ID);

// Mark identifier verified
UserHelper::markIdentifierVerified($user->ID, 'user@example.com');

// Check if all required verified
$allVerified = UserHelper::areAllRequiredIdentifiersVerified($user->ID, $requiredChannels);

// Activate user
UserHelper::activateUser($user->ID);

// Cleanup expired
$deleted = UserHelper::cleanupExpiredPendingUsers(24);
```

### 6. Channel Settings & Configuration

- **Location**: `Helpers/ChannelSettingsHelper.php`, `Settings/Groups/OTPChannelSettings.php`
- **Features**:
  - Per-channel configuration
  - Authentication method selection
  - Requirement settings
  - TTL configuration
  - Policy management
  - MFA settings (separate from primary auth)

**Configuration Sections**:

#### Login & Registration Channels
Used for primary authentication during login and registration.

**Email Channel**:
- `enabled`: Enable/disable email channel
- `required`: Email required for registration
- `verify`: Verify email during signup
- `allow_password`: Allow password authentication
- `allow_otp`: Allow OTP verification
- `allow_magic`: Allow magic link verification
- `otp_digits`: OTP length (default 6)
- `password_is_required`: Force password requirement
- `allow_signin`: Allow signin with email
- `allow_username_on_login`: Allow username on login
- `expiry_seconds`: OTP expiration time

**Phone Channel**:
- `enabled`: Enable/disable phone channel
- `required`: Phone required for registration
- `verify`: Verify phone during signup
- `allow_otp`: Allow OTP verification
- `allow_magic`: Allow magic link verification
- `otp_digits`: OTP length (default 6)
- `password_is_required`: Force password requirement
- `allow_signin`: Allow signin with phone
- `expiry_seconds`: OTP expiration time
- `sms`: Enable SMS delivery
- `whatsapp`: Enable WhatsApp delivery (coming soon)
- `viber`: Enable Viber delivery (coming soon)
- `call`: Enable phone call delivery (coming soon)

#### Multi-Factor Authentication (MFA)
Used for second-factor authentication after primary login.

**Email MFA**:
- `enabled`: Enable email as MFA method
- `required`: Require for all users
- `allow_otp`: Allow MFA OTP codes
- `allow_magic`: Allow MFA magic links
- `otp_digits`: MFA OTP length
- `expiry_seconds`: MFA OTP expiration
- **Note**: Disabled if email is used for primary auth

**Phone MFA**:
- `enabled`: Enable phone as MFA method
- `required`: Require for all users
- `allow_otp`: Allow MFA OTP codes
- `allow_magic`: Allow MFA magic links
- `otp_digits`: MFA OTP length
- `expiry_seconds`: MFA OTP expiration
- `sms`: Enable SMS delivery
- `whatsapp`: Enable WhatsApp delivery (coming soon)
- **Note**: Disabled if phone is used for primary auth

**TOTP MFA** (Coming Soon):
- `enabled`: Enable TOTP authenticator apps
- `required`: Require for all users
- `issuer`: Name shown in authenticator app
- `digits`: TOTP code length (6-8)
- `period`: Refresh interval (default 30s)

**Biometric MFA** (Coming Soon):
- `enabled`: Enable WebAuthn biometric auth
- `required`: Require for all users
- `attestation`: Security level (none, indirect, direct)
- `user_verification`: Require biometric/PIN (required, preferred, discouraged)

**Policies**:
- `ttl`: Time-to-live configurations
- `rate_limits`: Rate limiting rules

### 7. Event Logging & Analytics

- **Location**: `Models/AuthEventModel.php`
- **Features**:
  - Comprehensive event tracking
  - Flow-based correlation
  - Geo-location support
  - Device detection
  - Vendor status tracking
  - Configurable retention

**Event Types**:
- `register_init`: Registration started
- `register_verify`: Verification attempted
- `register_add_identifier`: Additional identifier added
- `login_attempt`: Login attempted
- `login_success`: Login successful
- `login_failed`: Login failed

**Logged Data**:
```json
{
  "event_id": "uuid",
  "flow_id": "flow_123",
  "timestamp_utc": "2024-01-15 10:30:00",
  "user_id": 123,
  "channel": "email",
  "event_type": "register_verify",
  "result": "allow",
  "client_ip_masked": "192.168.1.1",
  "geo_country": "US",
  "wp_role": "subscriber",
  "vendor_sid": "msg_123",
  "vendor_status": "sent",
  "attempt_count": 1,
  "user_agent": "...",
  "device_type": "desktop"
}
```

### 8. Admin Interface

- **Location**: `Admin/Pages/OTPAdminPage.php`
- **Features**:
  - React-based UI
  - Real-time settings management
  - Channel configuration
  - Policy management
  - Activity monitoring

### 9. Reports & Analytics

- **Location**: `Admin/Reports/Pages/ActivityOverviewReportPage.php`
- **Widgets**:
  - Health Snapshot: KPI dashboard
  - Journey Funnels: Conversion tracking
  - Volume Over Time: Activity trends
  - Method Mix: Auth method distribution
  - Delivery Quality: Success rates
  - Geo Heatmap: Geographic distribution

---

## Technical Implementation

### Authentication Flow Pipeline

The registration endpoints use a clean, modular approach without complex context arrays:

**Main Handler Pattern**
```php
public function handleRequest(WP_REST_Request $request)
{
    try {
        // Extract and validate data
        // Perform checks (rate limit, availability, etc.)
        // Execute business logic
        // Send artifacts
        // Log and respond
    } catch (\Exception $e) {
        return $this->handleException($e, 'operation');
    }
}
```

**Processing Flow**
1. Extract and normalize request data
2. Rate limit enforcement via `checkRateLimits()`
3. Availability checks via `checkAvailability()`
4. User resolution via helpers
5. Channel settings loading
6. Validation with WordPress callbacks
7. Artifact generation (OTP/Magic Link)
8. Delivery via `sendArtifacts()`
9. Logging and rate limit updates
10. Response building via `buildResponse()`

This approach provides:
- Clean, readable code without context arrays
- WordPress-native validation callbacks
- Clear separation of concerns
- Easy debugging
- Flexible error handling
- Maintainable and testable code

### Model Layer

All models extend `AbstractBaseModel` and provide:
- Type-safe properties
- CRUD operations
- Query builders
- Validation

**Example Model**:
```php
class OtpSessionModel extends AbstractBaseModel
{
    public ?string $flow_id = null;
    public ?string $identifier = null;
    public ?string $code_hash = null;
    
    protected static function getTableName(): string
    {
        return static::table('sms_otp_sessions');
    }
    
    public static function createSession(
        string $flowId, 
        string $code, 
        int $expiresInSeconds,
        string $identifier,
        string $channel = 'sms'
    ) {
        // Implementation
    }
}
```

### Error Handling

All endpoints use consistent error handling:

```php
try {
    // Process
    return $this->buildSuccessResponse($ctx);
} catch (WP_Error $we) {
    return $we;
} catch (\Exception $e) {
    return $this->handleException($e, 'operation');
}
```

Errors include:
- HTTP status codes
- User-friendly messages
- Actionable error types
- Context preservation

---

## API Reference

### REST Endpoints

All endpoints are under `/wp-json/wpsms/v1/` namespace:

### Registration Endpoints

#### GET `/register/init`
- **Purpose**: Initialize registration and get channel settings
- **Authentication**: Public
- **Response**: Channel configuration and policies

#### POST `/register/start`
- **Purpose**: Start registration flow
- **Parameters**:
  - `identifier` (string, required): Email or phone number
- **Response**: Flow information and verification options

#### POST `/register/verify`
- **Purpose**: Verify code/magic link or skip optional identifier
- **Parameters**:
  - `flow_id` (string, required): Flow identifier
  - `action` (string, optional): "verify" or "skip" (default: "verify")
  - `otp_code` (string, optional): OTP code
  - `magic_token` (string, optional): Magic link token
- **Response**: Verification status and next steps

#### POST `/register/add-identifier`
- **Purpose**: Add additional required identifier
- **Parameters**:
  - `flow_id` (string, required): Flow identifier
  - `identifier` (string, required): New identifier
- **Response**: New flow information

### Login Endpoints

#### GET `/login/init`
- **Purpose**: Initialize login and get channel settings
- **Authentication**: Public
- **Response**: Channel configuration and policies

#### POST `/login/start`
- **Purpose**: Start login flow
- **Parameters**:
  - `identifier` (string, required): Email, phone, or username
- **Response**: Flow information, verification options, and MFA status

#### POST `/login/verify`
- **Purpose**: Verify primary authentication
- **Parameters**:
  - `flow_id` (string, required): Flow identifier
  - `otp_code` (string, optional): OTP code
  - `magic_token` (string, optional): Magic link token
  - `password` (string, optional): User password
- **Response**: MFA requirement or login success

#### POST `/login/mfa-challenge`
- **Purpose**: Send MFA challenge
- **Parameters**:
  - `flow_id` (string, required): Original flow identifier
  - `mfa_method` (string, required): MFA method to use (email, phone, totp, biometric)
- **Response**: MFA challenge flow information

#### POST `/login/mfa-verify`
- **Purpose**: Verify MFA challenge and complete login
- **Parameters**:
  - `flow_id` (string, required): Original flow identifier
  - `mfa_flow_id` (string, required): MFA flow identifier
  - `otp_code` (string, optional): MFA OTP code
  - `magic_token` (string, optional): MFA magic token
  - `totp_code` (string, optional): TOTP code
- **Response**: Login success with auth token

---

## Database Schema

### Tables

#### `sms_otp_sessions`
Stores OTP generation and validation data.

```sql
CREATE TABLE `sms_otp_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `flow_id` CHAR(36) NOT NULL,
  `identifier` VARCHAR(255) NOT NULL,
  `identifier_type` ENUM('phone', 'email') NOT NULL,
  `otp_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempt_count` INT NOT NULL DEFAULT 0,
  `channel` VARCHAR(32) NOT NULL DEFAULT 'sms',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_flow_id` (`flow_id`),
  KEY `idx_otp_identifier` (`identifier`, `identifier_type`),
  KEY `idx_otp_expires` (`expires_at`)
);
```

#### `sms_magic_links`
Stores magic link generation and validation data.

```sql
CREATE TABLE `sms_magic_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `flow_id` CHAR(36) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `identifier` VARCHAR(255) NOT NULL,
  `identifier_type` ENUM('phone', 'email') NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_flow_id` (`flow_id`),
  KEY `idx_magic_identifier` (`identifier`, `identifier_type`),
  KEY `idx_magic_expires` (`expires_at`)
);
```

#### `sms_identifiers`
Stores verified user identifiers (MFA factors).

```sql
CREATE TABLE `sms_identifiers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `factor_type` ENUM('phone','email','totp','webauthn','backup') NOT NULL,
  `factor_value` VARBINARY(255) NOT NULL,
  `value_hash` CHAR(64) NOT NULL,
  `verified` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` DATETIME NOT NULL,
  `verified_at` DATETIME NULL,
  `last_used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_factor_type_value_hash` (`factor_type`, `value_hash`),
  KEY `idx_mfa_user` (`user_id`),
  KEY `idx_mfa_value_hash` (`value_hash`),
  KEY `idx_mfa_user_type` (`user_id`, `factor_type`)
);
```

#### `sms_auth_events`
Comprehensive event logging.

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

---

## Security Features

### 1. Rate Limiting
- Per-identifier limiting
- Per-IP limiting
- Configurable windows
- Automatic cleanup

### 2. Token Security
- OTP: SHA-256 hashed storage
- Magic Link: SHA-256 hashed tokens
- One-time use enforcement
- Automatic expiration

### 3. Identifier Validation
- Format validation
- Normalization
- Uniqueness checks
- Availability verification

### 4. Flow Security
- Unique flow IDs
- Time-bound sessions
- Attempt tracking
- Automatic invalidation

### 5. Data Protection
- Hashed storage (OTP, tokens, identifiers)
- Masked identifiers in responses
- IP masking support
- Secure metadata handling

---

## Administration

### Admin Page
Located at: `WP Admin > OTP/MFA`

**Features**:
- Channel settings management
- Policy configuration
- Activity monitoring
- Analytics dashboard
- Log viewing

### Settings Structure

Settings are managed via WordPress Options API:
- Namespace: `otp_channel_{channel}_{setting}`
- Examples:
  - `otp_channel_email`
  - `otp_channel_email_required_signup`
  - `otp_channel_email_verification_method`
  - `otp_channel_email_expiry_seconds`

---

## Code Patterns & Best Practices

> **Note**: As of Version 2.0.0, all registration endpoints have been refactored to use clean, linear handlers instead of complex context arrays. This improves code readability, maintainability, and follows WordPress best practices.

### 1. Service Pattern
All services implement interfaces and use dependency injection:

```php
class OtpService implements AuthChannelInterface
{
    protected DeliveryChannelManager $channelManager;
    
    public function __construct()
    {
        $this->channelManager = new DeliveryChannelManager();
    }
}
```

### 2. Model Pattern
Models extend base model and use static methods:

```php
class OtpSessionModel extends AbstractBaseModel
{
    protected static function getTableName(): string
    {
        return static::table('sms_otp_sessions');
    }
    
    public static function createSession(...) {
        // Static factory method
    }
}
```

### 3. Helper Pattern
Helpers provide static utility methods:

```php
class UserHelper
{
    public static function createPendingUser(string $identifier, array $customMeta = [])
    {
        // Implementation
    }
}
```

### 4. Clean Handler Pattern
REST endpoints use a clean, linear flow without context arrays:

```php
public function handleRequest(WP_REST_Request $request)
{
    try {
        // Extract data
        $identifier = (string) $request->get_param('identifier');
        
        // Validate and check
        $rateLimitCheck = $this->checkRateLimits(...);
        if ($rateLimitCheck instanceof WP_REST_Response) {
            return $rateLimitCheck;
        }
        
        // Execute logic
        // ...
        
        // Build and return response
        return $this->buildResponse(...);
    } catch (\Exception $e) {
        return $this->handleException($e, 'operation');
    }
}
```

### 5. Configuration Pattern
Settings accessed via helper:

```php
class ChannelSettingsHelper
{
    public static function getChannelData($channelName)
    {
        // Load from options
    }
}
```

---

## Integration Guide

### Adding a New Delivery Channel

1. **Create Channel Class**:
```php
class WhatsAppChannel implements DeliveryChannelInterface
{
    public function getKey(): string
    {
        return 'whatsapp';
    }
    
    public function send(string $to, string $message, array $context = []): bool
    {
        // Implementation
    }
}
```

2. **Register in Manager**:
```php
$channelManager->register(new WhatsAppChannel());
```

3. **Add Templates**:
```php
// In WhatsAppTemplate.php
class WhatsAppTemplate
{
    const TYPE_OTP_CODE = 'whatsapp_otp_code';
    // ... more types
}
```

### Adding a New Authentication Channel

1. **Implement Interface**:
```php
class TotpService implements AuthChannelInterface
{
    public function getKey(): string
    {
        return 'totp';
    }
    
    public function generate(...) { }
    public function validate(...) { }
    public function exists(...) { }
    public function invalidate(...) { }
}
```

2. **Register in Manager**:
```php
$authChannelManager->register(new TotpService());
```

### Customizing Templates

Templates are stored separately for each delivery channel:

**Email Templates**: `Delivery/Email/Templating/`
**SMS Templates**: `Delivery/PhoneNumber/Templating/`

Each template registry supports:
- Variable substitution
- Sanitization callbacks
- HTML/Plain text variants
- Multi-language support

### Hooks & Filters

**User Hooks**:
- `wpsms_pending_user_created`
- `wpsms_user_identifier_updated`
- `wpsms_user_activated`
- `wpsms_identifier_verified`

**Event Hooks**:
- `wpsms_email_before_send`
- `wpsms_sms_before_send`
- `wpsms_log_event`

**Usage**:
```php
add_action('wpsms_pending_user_created', function($user, $identifier, $meta) {
    // Custom logic
});
```

---

## Testing

### Unit Testing

Models and helpers should be unit tested:

```php
class OtpSessionModelTest extends TestCase
{
    public function test_create_session()
    {
        $session = OtpSessionModel::createSession(...);
        $this->assertNotNull($session);
    }
}
```

### Integration Testing

API endpoints should be integration tested:

```php
class RegisterEndpointTest extends TestCase
{
    public function test_start_registration()
    {
        $response = $this->post('/wp-json/wpsms/v1/register/start', [
            'identifier' => 'user@example.com'
        ]);
        
        $this->assertEquals(200, $response->get_status());
    }
}
```

---

## Troubleshooting

### Common Issues

1. **Rate Limiting**: Check transient expiration
2. **Token Expiration**: Verify TTL settings
3. **Delivery Failures**: Check channel configuration
4. **Identifier Conflicts**: Verify uniqueness constraints

### Debug Mode

Enable WP_DEBUG for detailed logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## Future Enhancements

Potential additions:
- WhatsApp delivery channel
- TOTP support
- WebAuthn support
- Social login integration
- Biometric authentication
- Advanced analytics
- A/B testing for templates
- Multi-language support

---

## Support & Maintenance

### Memory Considerations
- Always use models for database interaction
- Clean up expired sessions regularly
- Implement proper indexes
- Monitor query performance

### Performance Tips
- Use caching for settings
- Implement query optimization
- Use transients for rate limiting
- Batch operations where possible

---

## Changelog

### Version 2.2.0
- **IP Whitelist Feature**: Trust specific IP addresses to bypass security restrictions
  - Textarea-based configuration in new OTPSettings group
  - Support for IPv4, IPv6, and CIDR notation
  - Bypass rate limiting (configurable)
  - Bypass MFA requirements (configurable)
  - Automatic event logging with whitelist flags
  - Seamless integration into all authentication flows
- **WhitelistHelper**: Core helper class for IP validation and checking
- **MFA Enforcement**: Control which users/roles are required to use MFA
  - Four enforcement strategies (All Users, Specific Roles, Specific Users, Combined)
  - Role-based enforcement with multi-select
  - User-specific enforcement (by ID, username, or email)
  - Configurable grace period (0-90 days)
  - Smart reminder system (Every Login, Daily, Weekly, Never)
  - Skip option during grace period
  - Compliance statistics and tracking
- **MfaEnforcementHelper**: Comprehensive helper for MFA policy management
- **Redirect Management**: Control post-authentication redirects
  - Global login/register redirect URLs
  - Role-based redirect configuration
  - Shortcode attribute overrides
  - ?redirect_to parameter preservation
  - Auto-login after registration toggle
  - Open redirect prevention
- **RedirectHelper**: URL sanitization and priority-based redirect logic
- **Password Reset**: Secure password recovery via OTP/Magic Link
  - 3-step flow (init, verify, complete)
  - Multi-channel support (email, phone)
  - Configurable token expiry (5-60 minutes)
  - Auto-login after reset option
  - Identifier verification requirement
  - Conditional availability (password-based auth only)
  - User enumeration prevention
- **PasswordResetHelper**: Password reset configuration and business logic
- **Enhanced Security**: Fine-grained control over trusted networks and MFA requirements
- **Postman Collection**: Complete API testing collection with auto-variables
- See `IP_WHITELIST_DOCUMENTATION.md`, `MFA_ENFORCEMENT_DOCUMENTATION.md`, `REDIRECT_MANAGEMENT_DOCUMENTATION.md`, and `PASSWORD_RESET_DOCUMENTATION.md` for full details

### Version 2.1.0
- **Login API Endpoints**: Complete login flow with MFA support
  - `/login/init` - Initialize login
  - `/login/start` - Start login with identifier
  - `/login/verify` - Verify primary authentication
  - `/login/mfa-challenge` - Send MFA challenge
  - `/login/mfa-verify` - Verify MFA and complete login
- **MFA Configuration**: Separate MFA settings section
  - Email MFA (OTP, Magic Link)
  - Phone MFA (OTP, Magic Link, SMS, WhatsApp)
  - TOTP MFA (Coming Soon)
  - Biometric/WebAuthn MFA (Coming Soon)
- **Mutual Exclusion**: Channels can't be used for both primary auth and MFA
- **Skip Feature**: Optional identifiers can be skipped during registration
- **Enhanced Helpers**: MFA channel data methods in ChannelSettingsHelper
- **Account & MFA Management**: User profile and MFA settings pages

### Version 2.0.0
- **Refactored API Endpoints**: Removed complex context arrays in favor of clean, linear handlers
- **WordPress-Native Validation**: All endpoints now use WordPress validation callbacks
- **Improved Code Quality**: Better type safety, clearer error handling, enhanced maintainability
- **Simplified Architecture**: Reduced from multi-stage pipelines to straightforward method calls

### Version 1.0.0
- Initial release
- OTP and Magic Link support
- Multi-channel delivery
- Admin interface
- Analytics dashboard

---

## License

This service is part of the WP-SMS plugin and follows the same license terms.

---

## Contributors

Created by VeronaLabs for the WP-SMS plugin ecosystem.

