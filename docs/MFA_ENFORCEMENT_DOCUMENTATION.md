# MFA Enforcement Documentation

**Version:** 1.0.0  
**Last Updated:** November 2025

---

## Table of Contents

1. [Overview](#overview)
2. [Purpose & Benefits](#purpose--benefits)
3. [Enforcement Strategies](#enforcement-strategies)
4. [Configuration](#configuration)
5. [Grace Period & Reminders](#grace-period--reminders)
6. [Helper Methods](#helper-methods)
7. [Integration Examples](#integration-examples)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)
10. [Changelog](#changelog)

---

## Overview

The MFA Enforcement feature allows administrators to control **who** is required to use multi-factor authentication (2FA/MFA) based on **user roles** or **specific users**. It enables fine-grained enforcement policies, ensuring only selected users or roles (e.g., administrators, shop managers, editors) must complete a second authentication factor during login.

### Key Features

- **Role-Based Enforcement**: Require MFA for all users with specific roles
- **User-Specific Enforcement**: Require MFA for individual users regardless of role
- **Role Exclusions**: Exclude certain roles from global MFA requirements
- **Grace Period**: Give users time to set up MFA before enforcement begins
- **Smart Reminders**: Configurable reminder system during grace period
- **Compliance Tracking**: Statistics on MFA adoption across your user base
- **Flexible Strategies**: Multiple enforcement approaches to fit your security needs

### Why MFA Enforcement?

MFA enforcement is crucial for:
- **Sensitive Accounts**: Admins, shop managers, and editors need stronger security
- **Compliance**: Meet regulatory requirements (PCI-DSS, GDPR, HIPAA, etc.)
- **Risk Management**: Apply security controls proportional to user privileges
- **User Experience**: Minimize friction for low-privilege users while securing high-risk accounts

---

## Purpose & Benefits

### Security Benefits

1. **Targeted Protection**
   - Apply MFA only where it matters most
   - Reduce attack surface for privileged accounts
   - Balance security and usability

2. **Compliance**
   - Meet regulatory requirements for admin account security
   - Audit trail of MFA enforcement
   - Demonstrate security due diligence

3. **Risk Mitigation**
   - Prevent credential stuffing attacks on admin accounts
   - Reduce impact of password breaches
   - Protect against account takeovers

### Use Cases

| Use Case | Strategy | Example |
|----------|----------|---------|
| **E-commerce Site** | Specific Roles | Require MFA for Shop Manager, Editor |
| **Corporate Site** | Specific Roles | Require MFA for Administrator, Editor |
| **High-Security Site** | All Users | Require MFA for everyone except Subscribers |
| **Agency/Multi-site** | Specific Users | Require MFA for client admins only |
| **Gradual Rollout** | Specific Users | Start with admins, expand over time |

---

## Enforcement Strategies

The system supports four enforcement strategies:

### 1. All Users (Global)

**Description:** Require MFA for all users, with optional role exclusions.

**Configuration:**
```
Strategy: All Users
Excluded Roles: Subscriber, Customer
```

**Use Case:** High-security environments where most users need MFA, but you want to exclude low-privilege roles.

**Example:**
- Require MFA for everyone
- Exclude: Subscriber, Customer
- Result: Admins, Editors, Authors, Contributors need MFA

---

### 2. Specific Roles

**Description:** Require MFA only for users with selected roles.

**Configuration:**
```
Strategy: Specific Roles
Required Roles: Administrator, Shop Manager, Editor
```

**Use Case:** Most common scenario - protect privileged accounts only.

**Example:**
- WordPress admin and content managers need MFA
- Regular users don't

---

### 3. Specific Users

**Description:** Require MFA for individual users regardless of their role.

**Configuration:**
```
Strategy: Specific Users
Required Users:
  admin
  john_doe
  42 (user ID)
  jane@example.com
```

**Use Case:** Granular control for specific high-value accounts or gradual rollout.

**Example:**
- CEO account (regardless of role)
- External consultants with admin access
- Test users during pilot program

---

### 4. Specific Roles and Users

**Description:** Combine role-based and user-specific enforcement.

**Configuration:**
```
Strategy: Specific Roles and Users
Required Roles: Administrator, Editor
Required Users: john_doe, jane@example.com
```

**Use Case:** Comprehensive enforcement covering both privileged roles and specific individuals.

**Example:**
- All admins and editors need MFA
- Plus specific shop managers or authors
- Maximum flexibility

---

## Configuration

### Admin Settings

Navigate to: **WP-SMS → Settings → OTP Settings → MFA Enforcement**

### Available Settings

#### 1. Enable MFA Enforcement

```
Type: Checkbox
Default: Disabled
Description: Master switch for MFA enforcement
```

**When disabled:** MFA is optional for all users (current behavior)  
**When enabled:** Enforcement rules apply based on strategy

---

#### 2. Enforcement Strategy

```
Type: Select
Options:
  - All Users (Global)
  - Specific Roles
  - Specific Users
  - Specific Roles and Users
Default: Specific Roles
```

Determines how enforcement rules are applied. See [Enforcement Strategies](#enforcement-strategies) for details.

---

#### 3. Required Roles

```
Type: Multi-select
Visible: When strategy includes role-based enforcement
Available Options: All WordPress roles (Administrator, Editor, Author, etc.)
```

**Example Configuration:**
```
✓ Administrator
✓ Shop Manager
✓ Editor
  Author
  Contributor
```

**Result:** Users with any checked role must use MFA.

---

#### 4. Required Users

```
Type: Textarea (one per line)
Visible: When strategy includes user-specific enforcement
Accepts: User IDs, usernames, or email addresses
```

**Example Configuration:**
```
# Company executives
admin
ceo
42

# External consultants
john.consultant@agency.com
jane_smith
```

**Supported Formats:**
- Username: `admin`, `john_doe`
- User ID: `42`, `123`
- Email: `user@example.com`
- Comments: Lines starting with `#` are ignored

---

#### 5. Excluded Roles

```
Type: Multi-select
Visible: When strategy is "All Users"
Available Options: All WordPress roles
```

Use this to exempt certain roles from global MFA enforcement.

**Example:**
```
Strategy: All Users
Excluded Roles: Subscriber, Customer

Result: Everyone except Subscribers and Customers needs MFA
```

---

#### 6. Grace Period (days)

```
Type: Number
Range: 0-90 days
Default: 7 days
```

Number of days users have to set up MFA before enforcement begins.

- **0 days**: Immediate enforcement
- **7 days**: One week grace period (recommended)
- **30 days**: One month for gradual rollout

**During grace period:**
- Users can log in without MFA
- Reminders are shown (if enabled)
- After grace period: MFA is required

---

#### 7. Allow Skip During Grace Period

```
Type: Checkbox
Default: Enabled
```

When enabled, users can dismiss MFA setup prompts during grace period.

**Enabled:** Users see "Set up later" option  
**Disabled:** Users must set up MFA immediately

---

#### 8. Reminder Frequency

```
Type: Select
Options:
  - Every Login
  - Daily
  - Weekly
  - Never
Default: Daily
```

How often to remind users to set up MFA during grace period.

**Every Login:** Show reminder at every login  
**Daily:** Show once per day  
**Weekly:** Show once per week  
**Never:** No reminders (silent grace period)

---

## Grace Period & Reminders

### How Grace Period Works

1. **Enforcement Start Date**
   - When a user first becomes subject to MFA enforcement
   - Automatically set on first login after enforcement is enabled
   - Stored in user meta: `otp_mfa_enforcement_start_date`

2. **Grace Period Calculation**
   ```
   Grace End Date = Start Date + Grace Period Days
   ```

3. **During Grace Period**
   - User can log in without MFA (if "Allow Skip" is enabled)
   - Reminders are shown based on frequency setting
   - User can set up MFA at any time

4. **After Grace Period**
   - MFA is required
   - User cannot skip
   - Must complete MFA setup to access account

### Reminder System

**Reminder Logic:**

```php
Should Remind = 
  Enforcement Enabled AND
  User Required AND
  User Has Not Setup MFA AND
  User In Grace Period AND
  Time Since Last Reminder >= Reminder Frequency
```

**Reminder Tracking:**
- Last reminder timestamp stored in user meta: `otp_mfa_last_reminder`
- Updated each time reminder is shown
- Used to calculate next reminder time

**Example Timeline (7-day grace, daily reminders):**

| Day | Event |
|-----|-------|
| Day 0 | Enforcement enabled, grace period starts |
| Day 1 | Login → Reminder shown |
| Day 2 | Login → Reminder shown |
| Day 3 | User sets up MFA → No more reminders |
| Day 7 | Grace period ends |

---

## Helper Methods

All functionality is provided by the `MfaEnforcementHelper` class.

### Basic Checks

#### Check if enforcement is enabled

```php
use WP_SMS\Services\OTP\Helpers\MfaEnforcementHelper;

$enabled = MfaEnforcementHelper::isEnforcementEnabled();
```

#### Check if user is required to use MFA

```php
// By user ID
$required = MfaEnforcementHelper::isUserRequired($userId);

// By user object
$user = get_current_user();
$required = MfaEnforcementHelper::isUserRequired($user);
```

#### Check if user has MFA set up

```php
$hasSetup = MfaEnforcementHelper::hasUserSetupMfa($userId);
```

### Strategy & Configuration

#### Get enforcement strategy

```php
$strategy = MfaEnforcementHelper::getEnforcementStrategy();
// Returns: 'all_users', 'specific_roles', 'specific_users', 'roles_and_users'
```

#### Get required roles

```php
$roles = MfaEnforcementHelper::getRequiredRoles();
// Returns: ['administrator', 'shop_manager', 'editor']
```

#### Get excluded roles

```php
$roles = MfaEnforcementHelper::getExcludedRoles();
// Returns: ['subscriber', 'customer']
```

#### Get required users

```php
$users = MfaEnforcementHelper::getRequiredUsers();
// Returns: ['admin', 'john_doe', '42', 'user@example.com']
```

### Grace Period & Reminders

#### Check if user is in grace period

```php
$inGracePeriod = MfaEnforcementHelper::isUserInGracePeriod($userId);
```

#### Check if user should be reminded

```php
$shouldRemind = MfaEnforcementHelper::shouldRemindUser($userId);
```

#### Mark user as reminded

```php
MfaEnforcementHelper::markUserReminded($userId);
```

#### Get grace period settings

```php
$gracePeriod = MfaEnforcementHelper::getGracePeriod(); // Days
$canSkip = MfaEnforcementHelper::canSkipDuringGracePeriod(); // Boolean
$frequency = MfaEnforcementHelper::getReminderFrequency(); // String
```

### Advanced Methods

#### Get user enforcement summary

```php
$summary = MfaEnforcementHelper::getUserEnforcementSummary($userId);

/*
Returns:
[
  'enabled' => true,
  'required' => true,
  'has_setup' => false,
  'in_grace_period' => true,
  'can_skip' => true,
  'grace_period_days' => 7,
  'grace_period_days_remaining' => 5,
  'should_remind' => true,
  'strategy' => 'specific_roles',
  'enforcement_reasons' => ['role']
]
*/
```

#### Get enforcement statistics

```php
$stats = MfaEnforcementHelper::getEnforcementStatistics();

/*
Returns:
[
  'enabled' => true,
  'strategy' => 'specific_roles',
  'total_users' => 150,
  'required_users' => 25,
  'users_with_mfa' => 20,
  'users_without_mfa' => 5,
  'compliance_percentage' => 80.00
]
*/
```

---

## Integration Examples

### Example 1: Login Flow Integration

```php
use WP_SMS\Services\OTP\Helpers\MfaEnforcementHelper;

// In your login endpoint after primary authentication
public function handleLoginVerify($request) {
    // ... primary authentication successful ...
    
    $userId = $authenticatedUserId;
    
    // Check if MFA is required
    if (MfaEnforcementHelper::isUserRequired($userId)) {
        // Check if user has MFA set up
        if (!MfaEnforcementHelper::hasUserSetupMfa($userId)) {
            // User must set up MFA
            return $this->createErrorResponse(
                'mfa_setup_required',
                __('You must set up multi-factor authentication to continue', 'wp-sms'),
                403,
                [
                    'redirect' => '/account/mfa/setup',
                    'grace_period' => MfaEnforcementHelper::isUserInGracePeriod($userId),
                    'can_skip' => MfaEnforcementHelper::canSkipDuringGracePeriod() && 
                                  MfaEnforcementHelper::isUserInGracePeriod($userId)
                ]
            );
        }
        
        // User has MFA, require challenge
        return $this->sendMfaChallenge($userId);
    }
    
    // MFA not required, complete login
    return $this->completeLogin($userId);
}
```

### Example 2: Admin Dashboard Reminder

```php
use WP_SMS\Services\OTP\Helpers\MfaEnforcementHelper;

add_action('admin_notices', function() {
    $userId = get_current_user_id();
    
    if (MfaEnforcementHelper::shouldRemindUser($userId)) {
        $summary = MfaEnforcementHelper::getUserEnforcementSummary($userId);
        
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('MFA Setup Required', 'wp-sms'); ?></strong>
            </p>
            <p>
                <?php 
                printf(
                    __('You have %d days remaining to set up multi-factor authentication.', 'wp-sms'),
                    $summary['grace_period_days_remaining']
                );
                ?>
            </p>
            <p>
                <a href="<?php echo admin_url('profile.php#mfa-section'); ?>" class="button button-primary">
                    <?php _e('Set Up MFA Now', 'wp-sms'); ?>
                </a>
                <?php if ($summary['can_skip']): ?>
                <button class="button" onclick="dismissMfaReminder()">
                    <?php _e('Remind Me Later', 'wp-sms'); ?>
                </button>
                <?php endif; ?>
            </p>
        </div>
        <?php
        
        // Mark as reminded
        MfaEnforcementHelper::markUserReminded($userId);
    }
});
```

### Example 3: Admin Statistics Page

```php
use WP_SMS\Services\OTP\Helpers\MfaEnforcementHelper;

// Display MFA compliance statistics
function renderMfaStatistics() {
    $stats = MfaEnforcementHelper::getEnforcementStatistics();
    
    if (!$stats['enabled']) {
        echo '<p>' . __('MFA Enforcement is not enabled', 'wp-sms') . '</p>';
        return;
    }
    
    ?>
    <div class="mfa-stats">
        <h3><?php _e('MFA Enforcement Statistics', 'wp-sms'); ?></h3>
        
        <table class="widefat">
            <tr>
                <td><?php _e('Strategy', 'wp-sms'); ?></td>
                <td><?php echo esc_html($stats['strategy']); ?></td>
            </tr>
            <tr>
                <td><?php _e('Total Users', 'wp-sms'); ?></td>
                <td><?php echo number_format($stats['total_users']); ?></td>
            </tr>
            <tr>
                <td><?php _e('Required Users', 'wp-sms'); ?></td>
                <td><?php echo number_format($stats['required_users']); ?></td>
            </tr>
            <tr>
                <td><?php _e('Users with MFA', 'wp-sms'); ?></td>
                <td><?php echo number_format($stats['users_with_mfa']); ?></td>
            </tr>
            <tr>
                <td><?php _e('Users without MFA', 'wp-sms'); ?></td>
                <td><?php echo number_format($stats['users_without_mfa']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Compliance', 'wp-sms'); ?></strong></td>
                <td><strong><?php echo $stats['compliance_percentage']; ?>%</strong></td>
            </tr>
        </table>
    </div>
    <?php
}
```

---

## Best Practices

### 1. Start with Grace Period

```
✓ Enable 7-14 day grace period initially
✓ Allow skip during grace period
✓ Use daily reminders
✗ Don't enable immediate enforcement
```

**Why:** Gives users time to prepare and reduces support burden.

### 2. Communicate in Advance

- Announce MFA enforcement before enabling
- Provide setup instructions
- Offer support resources
- Set clear deadlines

### 3. Start with High-Privilege Roles

```
Phase 1: Administrator, Shop Manager
Phase 2: Editor, Author
Phase 3: All users (if needed)
```

**Why:** Protect most sensitive accounts first, monitor adoption, expand gradually.

### 4. Monitor Compliance

```php
// Weekly compliance check
$stats = MfaEnforcementHelper::getEnforcementStatistics();

if ($stats['compliance_percentage'] < 80) {
    // Send reminder emails
    // Extend grace period
    // Provide additional support
}
```

### 5. Exclude Low-Risk Roles

```
Strategy: All Users
Excluded: Subscriber, Customer
```

**Why:** Focus security efforts where they matter, reduce friction for casual users.

### 6. Use Role-Specific Grace Periods

While not built-in, you can implement this with custom code:

```php
// Longer grace for non-admins
$gracePeriod = user_can($userId, 'manage_options') ? 7 : 30;
```

### 7. Test with Specific Users First

```
Strategy: Specific Users
Users: admin, test_user
```

**Why:** Validate setup process before rolling out to entire roles.

---

## Troubleshooting

### Issue 1: User Says MFA Not Required But Should Be

**Check:**
1. Is enforcement enabled?
   ```php
   MfaEnforcementHelper::isEnforcementEnabled()
   ```

2. Is user's role required?
   ```php
   $user = get_user_by('id', $userId);
   MfaEnforcementHelper::isUserRoleRequired($user);
   ```

3. Is user in grace period?
   ```php
   MfaEnforcementHelper::isUserInGracePeriod($userId);
   ```

4. Check user summary:
   ```php
   $summary = MfaEnforcementHelper::getUserEnforcementSummary($userId);
   var_dump($summary);
   ```

---

### Issue 2: Reminders Not Showing

**Check:**
1. Reminder frequency setting
2. Last reminder timestamp
   ```php
   $lastReminder = get_user_meta($userId, 'otp_mfa_last_reminder', true);
   echo date('Y-m-d H:i:s', $lastReminder);
   ```
3. Should remind logic
   ```php
   $shouldRemind = MfaEnforcementHelper::shouldRemindUser($userId);
   ```

---

### Issue 3: User Locked Out After Grace Period

**Solution:** Temporarily extend grace period or manually mark MFA as set up:

```php
// Extend grace period by updating start date
$newStartDate = current_time('timestamp') - (6 * DAY_IN_SECONDS); // 6 days ago
update_user_meta($userId, 'otp_mfa_enforcement_start_date', $newStartDate);
```

---

### Issue 4: Role Not Appearing in Dropdown

**Cause:** Custom roles may not be available immediately.

**Solution:** Ensure roles are registered before settings page loads:

```php
add_action('init', function() {
    // Register custom role
    add_role('custom_role', 'Custom Role', []);
}, 5); // Priority 5 = before settings load
```

---

## Changelog

### Version 1.0.0 (November 2025)

**Initial Release**

- ✅ Four enforcement strategies (All Users, Specific Roles, Specific Users, Roles & Users)
- ✅ Role-based enforcement with multi-select
- ✅ User-specific enforcement with textarea input
- ✅ Role exclusions for global enforcement
- ✅ Configurable grace period (0-90 days)
- ✅ Smart reminder system (Every Login, Daily, Weekly, Never)
- ✅ Skip option during grace period
- ✅ MfaEnforcementHelper with comprehensive methods
- ✅ User enforcement summary
- ✅ Compliance statistics
- ✅ User meta tracking

**Components Added:**
- `OTPSettings.php` - MFA Enforcement section
- `MfaEnforcementHelper.php` - Core enforcement logic

**Future Enhancements (Planned):**
- Email notifications for grace period reminders
- Bulk MFA setup for admins
- Enforcement reports and logs
- Role-specific grace periods
- Custom enforcement rules via filters
- Integration with external MFA providers

---

**End of Documentation**

