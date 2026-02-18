# Repository Guidelines

## Project Structure

- `wp-sms.php`: plugin entry point; bootstraps core in `includes/`.
- `src/`: primary PHP code (PSR-4 `WP_SMS\\` via `composer.json`).
- `includes/`: legacy/core integration code, including `includes/gateways/` and REST API under `includes/api/v1/`.
- `public/src/`: blocks, SCSS, and scripts source; compiled outputs land in `public/css/`, `public/blocks/`, `public/js/`, etc.
- `resources/react/src/`: React dashboard (Vite + Tailwind).
- `tests/`: PHP unit tests and Playwright E2E (`tests/e2e/`).

## Build, Test, and Development Commands

Run from the plugin directory (`wp-content/plugins/wp-sms`):

```bash
npm install && composer install
npm run dashboard:dev        # Vite dev server for the React dashboard
npm run dashboard:build      # Build dashboard assets
npm run build                # Build Gutenberg blocks (wp-scripts)
npm run sass-compile         # One-off SCSS compilation
npm run test                 # Jest unit tests (JS)
composer test                # PHPUnit unit tests (PHP)
npm run wp:docker:start      # Start WP via @wordpress/env (http://localhost:8888)
npm run e2e                  # Playwright E2E (expects Docker env running)
```

## Coding Style & Naming

- Follow `.editorconfig` (4 spaces, LF). Keep changes consistent with surrounding code.
- PHP: prefer namespaced code in `src/`; sanitize inputs and escape outputs (`sanitize_*`, `esc_*`, `wp_kses_post()`).
- JS/React: use existing patterns in `resources/react/src/` (Tailwind + shadcn components).
- When doing automated edits (codemods) on JS/JSX in PowerShell, do not use default `Set-Content`/`Out-File` without an explicit UTF-8 encoding; it can introduce BOMs or mojibake in user-visible strings.
- Tests: PHP files end with `*Test.php`; Jest tests use `*.test.js`/`*.spec.js` or `__tests__/`.

## Testing Guidelines

- PHPUnit: configured in `phpunit.xml.dist` (coverage output under `tests/coverage/`).
- Jest: configured in `jest.config.js` (covers `public/src/settings/`, `resources/react/src/`, and `tests/js/`).
- Playwright: configured in `playwright.config.js`; E2E tests live in `tests/e2e/tests/` and run single-worker for WP stability.

## Commit & Pull Request Guidelines

- Commit messages commonly use `type: summary` (e.g., `feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`). Keep summaries imperative and scoped.
- After every change or task, run `npm run dashboard:build` to ensure the React dashboard compiles (and `npm run build` if blocks changed).
- PRs should include: what changed, why, how to test, and screenshots for admin/dashboard UI changes.
- Watch for backward compatibility (settings formats, REST/AJAX contracts) and WordPress.org wording (avoid "Pro" terminology in the free plugin UI).
