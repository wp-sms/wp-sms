# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP SMS (WSMS) is a WordPress plugin for sending SMS/MMS notifications through 200+ gateway providers. It integrates with WooCommerce, form builders (Contact Form 7, Gravity Forms, Formidable, Forminator), and provides subscriber management, OTP/2FA features, and a REST API.

## Development Commands

### Build Commands
```bash
# Install dependencies
npm install
composer install

# Build Gutenberg blocks
npm run build

# Build settings page (React/Vite)
npm run settings:build

# Compile SCSS
npm run sass-compile

# Compile legacy JS
npm run js-compile

# Development with hot reload
npm run start              # Blocks + settings dev mode
npm run settings:dev       # Settings page only (Vite, port 5177)
npm run watch              # SCSS + legacy JS
```

### Testing
```bash
# Unit tests (PHPUnit)
composer test

# JavaScript tests (Jest)
npm run test
npm run test:watch
npm run test:coverage

# E2E tests (Playwright, requires Docker)
npm run wp:docker:start    # Start WordPress environment
npm run e2e                # Run all E2E tests
npm run e2e:ui             # Interactive UI mode
npm run e2e:headed         # Run with browser visible
npm run e2e:smoke          # Smoke tests only
npm run e2e:a11y           # Accessibility tests
```

### Docker Environment
```bash
npm run wp:docker:start    # Start WordPress (port 8888)
npm run wp:docker:stop     # Stop WordPress
npm run wp:docker:clean    # Reset environment
npm run wp:docker:logs     # View logs
```

## Architecture

### PHP Code Structure

**Entry Point**: `wp-sms.php` loads `includes/class-wpsms.php` (main WP_SMS class, singleton pattern)

**Namespaces**: PSR-4 autoloading with `WP_SMS\` namespace mapping to `src/`

**Key Directories**:
- `src/` - Modern PSR-4 classes
  - `Admin/` - Admin pages, settings, onboarding wizard, license management
  - `Services/` - Business logic (WooCommerce, form builders, subscribers, cron jobs)
  - `Controller/` - AJAX handlers (all extend `AjaxControllerAbstract`)
  - `Components/` - Reusable components (Logger, etc.)
  - `Notification/` - Notification system with `NotificationFactory`
- `includes/` - Legacy classes and core functionality
  - `gateways/` - 200+ SMS gateway implementations (extend base Gateway class)
  - `api/v1/` - REST API endpoints (newsletter, send, webhook, settings, subscribers, groups)
  - `admin/` - Legacy admin pages
- `views/` - PHP template files

**Gateway System**: All gateways in `includes/gateways/` extend the base gateway class in `includes/class-wpsms-gateway.php`. The global `$sms` object is the initialized gateway instance.

### Frontend Assets

**Settings Page** (React/Vite):
- Source: `assets/src/settings/`
- Output: `assets/dist/settings/`
- Uses Tailwind CSS, Radix UI, shadcn components
- Vite alias `@` maps to `assets/src/settings/`

**Gutenberg Blocks**:
- Source: `assets/src/blocks/`
- Output: `assets/blocks/`
- Built with `@wordpress/scripts`

**Legacy Assets**:
- SCSS: `assets/src/scss/` and `assets/src/admin/`
- JS: `assets/src/scripts/`
- Output: `assets/css/` and `assets/js/`

### REST API

Base namespace: `wpsms/v1`

Endpoints: `/send`, `/newsletter`, `/webhook`, `/credit`, `/settings`, `/subscribers`, `/groups`, `/outbox`, `/privacy`, `/notifications`

### Database Tables

Created via `includes/class-wpsms-install.php`:
- `{prefix}sms_subscribes` - Subscriber data
- `{prefix}sms_subscribes_group` - Subscriber groups
- `{prefix}sms_send` - Outbox/sent messages

## Key Functions and Hooks

**Send SMS**:
```php
wp_sms_send($to, $msg, $isFlash = false, $from = false, $mediaUrls = []);
```

**Filters**: `wp_sms_from`, `wp_sms_to`, `wp_sms_msg`

**Actions**: `wp_sms_send`, `wp_sms_add_subscriber`

## Add-on Settings System

Add-ons (like WP SMS WooCommerce Pro) register their settings for the React dashboard using the `wpsms_addon_settings_schema` filter.

### How It Works

1. **Schema Registration**: Add-ons hook into `wpsms_addon_settings_schema` to provide field definitions
   ```php
   // In add-on: src/Admin/ReactSettings/WooCommerceProSettingsSchema.php
   add_filter('wpsms_addon_settings_schema', [self::class, 'registerSchema']);
   ```

2. **Data Flow to React**: `UnifiedAdminPage::getLocalizedData()` passes two things:
   - `addonSettings` - Field schemas (structure, labels, types)
   - `addonValues` - Actual option values from database

3. **Value Conversion**: WooCommerce stores checkboxes as `'yes'`/`'no'` strings. The `getAddonOptionValues()` method converts these to boolean for React switch components.

### Key Files

- `src/Admin/UnifiedAdminPage.php` - Loads schema + values for React dashboard
- `assets/src/settings/components/ui/DynamicField.jsx` - Renders fields based on schema
- `assets/src/settings/context/SettingsContext.jsx` - Manages add-on state (`addonValues`)

### Adding New Add-on Settings

When creating settings for a new add-on:
1. Create a schema class (see `wp-sms-woocommerce-pro/src/Admin/ReactSettings/WooCommerceProSettingsSchema.php`)
2. Hook into `wpsms_addon_settings_schema` filter
3. Each field needs an `addonSlug` property to route values correctly
4. Use `'yes'`/`'no'` for checkbox defaults if WooCommerce compatibility is needed

## Testing Notes

- Unit tests in `tests/unit/` - run single test file: `./vendor/bin/phpunit tests/unit/HelperTest.php`
- E2E tests use Page Object Model pattern (`e2e/pages/`)
- E2E requires `.env.e2e` file (copy from `.env.e2e.example`)
- WordPress test environment runs on port 8888, tests on 8889

## Coding Standards

### Internationalization (i18n)
All user-facing strings must be translatable:
- **PHP**: Use `__('string', 'wp-sms')` or `esc_html__('string', 'wp-sms')`
- **React**: Use `__('string')` from `@/lib/utils` which reads from `wpSmsSettings.i18n`

### Security

**Escape all output** (prevent XSS):
```php
esc_html($text);           // For plain text
esc_attr($attribute);      // For HTML attributes
esc_url($url);             // For URLs
wp_kses_post($html);       // For HTML content with allowed tags
```

**Sanitize all input** (validate/clean user data):
```php
sanitize_text_field($input);      // For text input
sanitize_email($email);           // For email addresses
absint($number);                  // For positive integers
sanitize_textarea_field($text);   // For textarea content
```

### Responsive Design (React Settings Pages)

The React settings pages must be fully responsive across all viewport sizes. Use Tailwind CSS mobile-first approach:

**Breakpoints**:
- Default (no prefix): Mobile styles (< 768px)
- `lg:` (1024px): Use for sections with fewer elements (e.g., stats header with 3 items)
- `xl:` (1280px): Use for sections with more elements (e.g., filters with 4+ items, complex toolbars)

**Layout Patterns**:
```jsx
// Mobile: stacked, Desktop: horizontal
<div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center">

// Mobile: 2-column grid, Desktop: inline flex
<div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 xl:wsms-flex xl:wsms-items-center">

// Use wsms-contents for grid children that need to escape container
<div className="wsms-contents xl:wsms-flex xl:wsms-items-center">
```

**Guidelines**:
- Always test at multiple widths: mobile (~375px), tablet (~768px), laptop (~1024px), desktop (~1440px)
- Ensure no horizontal overflow at any width
- Use consistent breakpoints across similar sections (Outbox and Subscribers should behave the same)
- Desktop layouts should preserve the original design; mobile layouts should stack gracefully

## Commit Guidelines

**Important**: Always commit changes made during your active session before the session ends. Only commit files you modified - do not include changes from other concurrent Claude sessions.

Before committing changes, always:

1. **Review staged changes**: Run `git diff --staged` to review all changes that will be committed
2. **Check backward compatibility**: Ensure changes don't break existing functionality
   - Option keys must remain unchanged
   - API endpoints must maintain the same request/response format
   - Database schema changes require migration scripts
3. **Security review**: Verify all user input is sanitized, all output is escaped, and no SQL injection vulnerabilities exist
4. **Performance review**: Check for N+1 queries, unnecessary database calls, and inefficient loops
5. **Verify the build**: Run `npm run settings:build` to ensure no build errors
6. **Warn on risky changes**: If changes are potentially risky (e.g., modifying core functionality, changing database operations, altering API contracts), ask the user for confirmation before committing