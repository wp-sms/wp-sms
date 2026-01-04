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

## Testing Notes

- Unit tests in `tests/unit/` - run single test file: `./vendor/bin/phpunit tests/unit/HelperTest.php`
- E2E tests use Page Object Model pattern (`e2e/pages/`)
- E2E requires `.env.e2e` file (copy from `.env.e2e.example`)
- WordPress test environment runs on port 8888, tests on 8889