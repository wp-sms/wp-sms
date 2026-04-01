## Development Phase
This project is in active development. No backward compatibility, deprecation shims, or migration paths are needed — change schemas, APIs, and interfaces directly.

## Browser Automation

Use `agent-browser` for web automation. Run `agent-browser --help` for all commands.

- **WordPress credentials**: stored in `.env` file (`WP_USER`, `WP_PASS`)
- **Local site URL**: `http://wsms8.local/wp-admin/`

Core workflow:
1. `agent-browser open <url>` - Navigate to page
2. `agent-browser snapshot -i` - Get interactive elements with refs (@e1, @e2)
3. `agent-browser click @e1` / `fill @e2 "text"` - Interact using refs
4. Re-snapshot after page changes

## Auth Architecture

### Source of Truth: Settings
All auth behavior is driven by `wsms_settings` (WordPress option, referenced as `SettingsRepository::OPTION_KEY`). The canonical defaults live in `SettingsRepository::DEFAULTS` (`src/Auth/SettingsRepository.php`). Frontend defaults must stay synced in `resources/react/src/lib/constants.ts` (enforced by test).

### Channels & Methods
- **password**: enabled/disabled, required_at_signup
- **email**: enabled, usage (login|mfa), verification_methods (otp|magic_link), code_length (4|6), verify_at_signup, verify_at_login
- **phone**: same as email + required_at_signup
- **backup_codes**: MFA-only fallback

### Key Classes
- `PolicyEngine` — decides available methods, MFA requirements, pending verifications from settings
- `AuthOrchestrator` — coordinates login/verify/MFA flows, manages session stages
- `AccountManager` — registration, verification (email/phone OTP/magic link), password reset, profile updates
- `MfaManager` — channel registry, factor enrollment/verification

### Auth Flows
1. **Login**: identify → loginWithPassword / loginPasswordless → (verify_at_login?) → (MFA?) → authenticated
2. **Register**: registerUser → (verify_at_signup?) → pending_verifications or success
3. **MFA**: sendMfaChallenge → verifyMfa → authenticated
4. **Profile verify**: send-email/phone-verification → verify-email/phone (OTP or magic link)
5. **Password reset**: forgotPassword → resetPassword (token-based)

### Frontend Build
- Admin pages: `npx vite build` → outputs to `public/app/`
- Auth pages share `public/auth/` — **never run a single auth build in isolation**, always build all three together:
  ```bash
  npx vite build --config vite.config.auth.mjs && \
  npx vite build --config vite.config.verify-widget.mjs && \
  npx vite build --config vite.config.messaging-button.mjs
  ```
  Or use `npm run build` which handles ordering and cleanup automatically.

## Testing

### Frameworks
- **Backend**: PHPUnit (`composer test`). WordPress functions stubbed in `tests/bootstrap.php`.
- **Frontend**: Vitest + Testing Library + MSW (`npm test`). Config in `vitest.config.ts`.

### Test Organization
- `tests/unit/` — isolated unit tests (~430 tests), all deps mocked
- `tests/integration/` — multi-class flow tests (planned), real PolicyEngine + AuthOrchestrator + AccountManager with mocked I/O
- `tests/Support/` — shared helpers: `AuthScenarios`, `UserFactory`, `WpdbFake`, `IntegrationTestCase`

### Key Testing Patterns
- Use `@dataProvider` for settings × channel × flow combinations (see `PolicyEngineTest::primaryMethodsProvider`)
- `AuthScenarios` class provides named setting presets (source of truth for test configs)
- `UserFactory` replaces duplicated `makeUser()` helpers across test files
- `WpdbFake` tracks inserts/updates for assertions instead of brittle mock expectations
- Integration tests wire real domain classes, only mock I/O boundaries (wpdb, wp_mail, SMS)

### Scenario Matrix (when writing tests)
When testing auth changes, ensure coverage across these dimensions:
- **Channels**: password, email, phone (and combinations)
- **Methods**: OTP vs magic_link per channel
- **Signup flags**: verify_at_signup on/off per channel
- **Login flags**: verify_at_login on/off per channel
- **MFA**: enrollment_timing (on_registration, grace_period, voluntary) × required_roles
- **Edge cases**: expired tokens, max_attempts, rate limiting, session tampering

### E2E Tests (REST API)
- **Framework**: Vitest with real HTTP requests to a running WordPress instance (no mocking)
- **Config**: `tests/e2e/vitest.config.e2e.ts` — sequential execution (`maxWorkers: 1`, `fileParallelism: false`) since tests share WordPress state
- **Requires**: `WSMS_E2E_SECRET` env var set in both WordPress and test runner, plus the `wsms-e2e-test-helper.php` mu-plugin installed
- **OTP interception**: `do_action('wsms_otp_generated', ...)` hooks in AccountManager and MFA channels; mu-plugin captures plaintext codes via transients
- **Structure**:
  - `tests/e2e/helpers/` — api-client (cookie jar + nonce), auth-scenarios (16 presets), otp-interceptor, settings-manager, user-factory
  - `tests/e2e/flows/` — login, registration, verification, password-reset, profile, MFA flow tests
  - `tests/e2e/matrix/` — parameterized tests across all config presets (login × 7 presets, registration × 4, MFA × 5)
- **Important**: Never set `WSMS_E2E_SECRET` in production — the mu-plugin exposes plaintext OTPs for testing

### Running Tests
```bash
composer test                                    # All unit tests
./vendor/bin/phpunit --testsuite unit            # Unit only
./vendor/bin/phpunit --testsuite integration     # Integration only
./vendor/bin/phpunit --filter="AccountManager"   # Specific class
npm test                                         # Frontend tests
npm run test:e2e                                 # E2E tests (requires running WordPress)
npm run test:a11y                                # Live page a11y scan (requires running WordPress)
```

### Live Page A11y Scanning
- **Framework:** pa11y-ci with HTML_CodeSniffer + axe-core dual runners
- **Config:** `.pa11yci.cjs` (reads credentials from `.env`)
- **Requires:** Running WordPress instance at `WP_URL`
- **Run:** `npm run test:a11y`
- Scans auth pages (public) and admin dashboard pages (auto-login via session sharing)

## i18n

### ESLint
`react/jsx-no-literals` warns on raw English text in JSX. All user-visible strings must be wrapped with `{__('...', 'wp-sms')}`.

### PO Source References
All PO entries for JS strings must use `#: public/app/main.js:1 public/js/app/main.js:1` as source reference (not the `.tsx` source path), so `wp i18n make-json` includes them in the JSON files WordPress loads.
