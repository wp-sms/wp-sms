// .pa11yci.cjs — pa11y-ci config (CommonJS required)
const fs = require('fs');
const path = require('path');

// Parse .env without requiring dotenv
const envPath = path.join(__dirname, '.env');
const env = {};
try {
  fs.readFileSync(envPath, 'utf8').split('\n').forEach(line => {
    const match = line.match(/^\s*([\w]+)\s*=\s*(.*)$/);
    if (match) env[match[1]] = match[2].trim();
  });
} catch (_) {}

const BASE = env.WP_URL || process.env.WP_URL;
const user = env.WP_USERNAME || process.env.WP_USERNAME;
const pass = env.WP_PASSWORD || process.env.WP_PASSWORD;

if (!BASE || !user || !pass) {
  throw new Error(
    'Missing required env vars: WP_URL, WP_USERNAME, WP_PASSWORD. ' +
    'Set them in .env or as environment variables.'
  );
}

const ADMIN = `${BASE}/wp-admin/admin.php?page=wsms-auth`;

const loginActions = [
  `navigate to ${BASE}/wp-login.php`,
  `set field #user_login to ${user}`,
  `set field #user_pass to ${pass}`,
  `click element #wp-submit`,
  `wait for element #wpbody-content to be visible`,
];

const waitForApp =
  'wait for element [data-slot="sidebar-wrapper"] to be visible';

function adminPage(hash) {
  return {
    url: hash ? `${ADMIN}#${hash}` : ADMIN,
    actions: hash
      ? [`navigate to ${ADMIN}#${hash}`, waitForApp]
      : [waitForApp],
    rootElement: '#wpsms-app',
  };
}

module.exports = {
  defaults: {
    standard: 'WCAG2AA',
    runners: ['htmlcs', 'axe'],
    timeout: 60000,
    ignore: ['notice', 'warning'],
    viewport: { width: 1280, height: 900 },
    chromeLaunchConfig: {
      ignoreHTTPSErrors: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
    // Must be inside defaults — pa11y-ci only passes defaults to its runner.
    // false = share cookies across URLs so login session carries to admin pages.
    useIncognitoBrowserContext: false,
  },

  urls: [
    // ── Auth pages (public, no login needed) ──
    { url: `${BASE}/account/login`, rootElement: '#wsms-auth' },
    { url: `${BASE}/account/register`, rootElement: '#wsms-auth' },
    { url: `${BASE}/account/forgot-password`, rootElement: '#wsms-auth' },

    // ── WP Login (first admin entry — establishes session) ──
    {
      url: `${BASE}/wp-login.php`,
      actions: loginActions,
      // Suppress wp-login.php's own a11y issues — we only need the session
      ignore: ['notice', 'warning', 'error'],
    },

    // ── Admin dashboard pages (session from login carries over) ──
    adminPage(null),
    adminPage('/messaging/campaigns'),
    adminPage('/flows'),
    adminPage('/authentication'),
    adminPage('/logs'),
    adminPage('/settings'),
    adminPage('/messaging-button'),
    adminPage('/system'),
  ],
};
