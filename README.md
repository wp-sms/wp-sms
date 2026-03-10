# WSMS (formerly WP SMS)

SMS & MMS Notifications, 2FA, OTP, and Integrations with E-Commerce and Form Builders.

## Requirements

- PHP 7.4+
- WordPress 6.0+
- Node.js 20+

## Development Setup

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Start development servers (Vite + SASS watcher)
npm run dev

# Run tests
composer test
```

## Build

```bash
# Full production build (dashboard + scripts + sass + blocks)
npm run build

# Generate language POT file
wp i18n make-pot . public/languages/wp-sms.pot --slug=wp-sms --domain=wp-sms
```

## Project Structure

```
wp-sms/
├── wp-sms.php              # Plugin entry point (slim loader)
├── uninstall.php            # Cleanup on plugin deletion
├── src/                     # WSms\ namespace (PSR-4)
│   ├── Bootstrap.php        # Main initialization
│   ├── constants.php        # Plugin constants
│   ├── functions.php        # Public API functions
│   ├── Container/           # Service container + providers
│   └── Service/             # Feature services
├── compat/                  # WP_SMS\ backward compatibility shims
│   ├── autoload.php         # Legacy class autoloader
│   ├── functions.php        # Legacy function shims
│   └── classes/             # Empty shim classes
├── resources/               # Frontend source files
│   ├── react/               # React dashboard app (Vite)
│   ├── entries/             # Admin/frontend IIFE entry points
│   ├── scss/                # SASS stylesheets
│   └── blocks/              # Gutenberg blocks
├── public/                  # Build output
│   ├── dashboard/           # Vite React build
│   ├── js/                  # IIFE bundles
│   ├── css/                 # Compiled CSS
│   ├── blocks/              # wp-scripts block output
│   └── languages/           # Translation files
├── views/                   # PHP view templates
└── tests/                   # PHPUnit tests
```

## Architecture

- **Namespace:** `WSms\` for all new code
- **Backward Compatibility:** `WP_SMS\` shim classes in `compat/` prevent fatal errors in legacy add-ons
- **Service Container:** Lazy-loading singleton with factory registration
- **Frontend:** React 19 + Vite 6, enqueued as ES modules (WP 6.5+)

## License

GPL-2.0+
