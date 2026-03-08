import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import dotenv from 'dotenv';

// Load environment variables from .env.e2e
dotenv.config({ path: '.env.e2e' });

/**
 * WP-SMS Playwright E2E Test Configuration
 * Uses Docker (@wordpress/env) for WordPress environment
 *
 * Start WordPress: npm run wp:docker:start
 * Run tests: npm run e2e
 */
export default defineConfig({
  testDir: './tests/e2e/tests',

  // Run tests in files in parallel
  fullyParallel: false, // WordPress may have state issues with parallel tests

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Single worker for WordPress stability
  workers: process.env.CI ? 1 : 1,

  // Reporter configuration
  reporter: [
    ['html', { outputFolder: 'e2e-report' }],
    ['list'],
  ],

  // Shared settings for all projects
  use: {
    // Base URL for all tests (Docker @wordpress/env default)
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',

    // Take screenshot on failure
    screenshot: 'only-on-failure',

    // Record video on failure
    video: 'retain-on-failure',

    // Default timeout for actions
    actionTimeout: 10000,

    // Default navigation timeout
    navigationTimeout: 30000,
  },

  // Global setup and teardown
  globalSetup: './tests/e2e/global-setup.js',
  globalTeardown: './tests/e2e/global-teardown.js',

  // Test timeout
  timeout: 60000,

  // Projects for different browsers
  // To run all browsers: npx playwright test --project=chromium --project=firefox --project=webkit
  projects: [
    // Setup project for authentication
    {
      name: 'setup',
      testMatch: /auth\.setup\.js/,
      testDir: './tests/e2e',
    },

    // Chromium tests (default for local development)
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: path.join(__dirname, 'tests/e2e/.auth/user.json'),
      },
      dependencies: ['setup'],
    },

    // Firefox tests (disabled by default - enable for CI with --project=firefox)
    // {
    //   name: 'firefox',
    //   use: {
    //     ...devices['Desktop Firefox'],
    //     storageState: path.join(__dirname, 'tests/e2e/.auth/user.json'),
    //   },
    //   dependencies: ['setup'],
    // },

    // WebKit/Safari tests (disabled by default - enable for CI with --project=webkit)
    // {
    //   name: 'webkit',
    //   use: {
    //     ...devices['Desktop Safari'],
    //     storageState: path.join(__dirname, 'tests/e2e/.auth/user.json'),
    //   },
    //   dependencies: ['setup'],
    // },
  ],

  // Output directory for test artifacts
  outputDir: 'e2e-results/',

  // Expect configuration
  expect: {
    // Maximum time expect() should wait for the condition to be met
    timeout: 10000,
  },
});
