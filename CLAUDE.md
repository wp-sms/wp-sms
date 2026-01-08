# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Project Overview

WP SMS is a WordPress plugin for sending SMS/MMS through 200+ gateways. Integrates with WooCommerce, form builders (CF7, Gravity Forms, Formidable, Forminator), and provides subscriber management, OTP/2FA, and REST API.

## Commands

```bash
npm install && composer install   # Install dependencies
npm run settings:build            # Build React settings page
npm run build                     # Build Gutenberg blocks
npm run sass-compile              # Compile SCSS
composer test                     # PHPUnit tests
npm run test                      # Jest tests
npm run wp:docker:start           # Start WordPress (port 8888)
npm run e2e                       # Run E2E tests (requires Docker)
```

## Architecture

**Entry**: `wp-sms.php` → `includes/class-wpsms.php` (singleton)
**Namespace**: `WP_SMS\` maps to `src/` (PSR-4)

### Key Directories
- `src/Admin/` - Admin pages, settings, onboarding
- `src/Services/` - Business logic (WooCommerce, forms, subscribers)
- `src/Controller/` - AJAX handlers (extend `AjaxControllerAbstract`)
- `src/Notification/` - Notification system with `NotificationFactory`
- `includes/gateways/` - SMS gateway implementations (extend base Gateway class)
- `includes/api/v1/` - REST API endpoints
- `assets/src/settings/` - React settings page (Vite, Tailwind, shadcn)
- `assets/src/blocks/` - Gutenberg blocks

### REST API
Namespace: `wpsms/v1`
Endpoints: `/send`, `/newsletter`, `/webhook`, `/credit`, `/settings`, `/subscribers`, `/groups`, `/outbox`, `/privacy`, `/notifications`

### Database Tables
- `{prefix}sms_subscribes` - Subscribers
- `{prefix}sms_subscribes_group` - Groups
- `{prefix}sms_send` - Outbox/sent messages

## Key Functions

```php
wp_sms_send($to, $msg, $isFlash, $from, $mediaUrls);
```
**Filters**: `wp_sms_from`, `wp_sms_to`, `wp_sms_msg`
**Actions**: `wp_sms_send`, `wp_sms_add_subscriber`

## Add-on Settings

Add-ons register settings via `wpsms_addon_settings_schema` filter. See `wp-sms-woocommerce-pro` for example.

When adding new add-on settings:
1. Create schema class hooking into `wpsms_addon_settings_schema`
2. Include `addonSlug` property on each field
3. Use `'yes'`/`'no'` for checkbox defaults (WooCommerce compat)

## Additional Recipient Types (Send SMS Page)

**Note:** Avoid "Pro" terminology in main plugin (WordPress.org rules). Use "additional" or similar.

Add-ons register recipient types via `wpsms_additional_recipient_types` filter. Backend support required in `includes/api/v1/class-wpsms-api-send.php`.

## Testing

**Locations**: `tests/unit/` (PHP), `assets/src/**/__tests__/` (JS), `e2e/tests/` (Playwright)

**Write tests for**:
- REST API endpoints (success, errors, auth)
- AJAX handlers in `src/Controller/`
- Gateway integrations
- Business logic in `src/Services/`
- Input validation/sanitization

**Skip tests for**: WP core functions, simple getters/setters, third-party internals

## Coding Standards

**i18n**: PHP `__('string', 'wp-sms')` | React `__('string')` from `@/lib/utils`

**Security - Escape output**: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`

**Security - Sanitize input**: `sanitize_text_field()`, `sanitize_email()`, `absint()`, `sanitize_textarea_field()`

**Responsive**: Mobile-first with `lg:` (1024px) and `xl:` (1280px) breakpoints

## Commit Guidelines

**Always ask for user approval before committing.** Briefly explain what changed when asking.

**Prefixes**: `feat:` (new feature), `fix:` (bug fix), `refactor:` (code restructure), `docs:` (documentation), `test:` (tests), `chore:` (maintenance)

Before committing:
1. Run `git diff --staged` to review changes
2. Run `npm run settings:build` to verify build
3. Check: input sanitized, output escaped, no SQL injection
4. Warn user before committing risky changes (core functionality, DB operations, API contracts)

Only commit files you modified. Do not include changes from other sessions.
