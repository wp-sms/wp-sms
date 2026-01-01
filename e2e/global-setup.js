/**
 * Global Setup for Playwright E2E Tests
 *
 * This file runs before all tests to ensure WordPress is ready.
 * It does NOT start WordPress Playground - that should be done manually
 * or via npm scripts before running tests.
 */

import { chromium } from '@playwright/test';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const MAX_RETRIES = 30;
const RETRY_DELAY = 1000;

/**
 * Wait for WordPress to be accessible
 */
async function waitForWordPress() {
  console.log(`Waiting for WordPress at ${BASE_URL}...`);

  for (let i = 0; i < MAX_RETRIES; i++) {
    try {
      const response = await fetch(BASE_URL, { method: 'HEAD' });
      if (response.ok || response.status === 302) {
        console.log('WordPress is ready!');
        return true;
      }
    } catch (error) {
      // WordPress not ready yet
    }

    if (i < MAX_RETRIES - 1) {
      console.log(`WordPress not ready, retrying in ${RETRY_DELAY}ms... (${i + 1}/${MAX_RETRIES})`);
      await new Promise((resolve) => setTimeout(resolve, RETRY_DELAY));
    }
  }

  throw new Error(`WordPress did not become available at ${BASE_URL} after ${MAX_RETRIES} attempts`);
}

/**
 * Verify plugin is activated
 * Note: WordPress Playground auto-logs users in
 */
async function verifyPluginActive() {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  try {
    // Navigate directly to admin (WordPress Playground auto-logs in)
    await page.goto(`${BASE_URL}/wp-admin/`);
    await page.waitForLoadState('networkidle');

    // Navigate to WP-SMS NEW settings page (React app) to verify plugin is active
    await page.goto(`${BASE_URL}/wp-admin/admin.php?page=wp-sms-new-settings`);

    // Check if settings page loads (React app mounts)
    const settingsRoot = await page.waitForSelector('#wpsms-settings-root', { timeout: 15000 });
    if (settingsRoot) {
      console.log('WP-SMS plugin is active and settings page is accessible!');
    }
  } catch (error) {
    console.warn('Could not verify plugin activation:', error.message);
    console.log('Tests will continue, but some may fail if plugin is not active.');
  } finally {
    await browser.close();
  }
}

export default async function globalSetup() {
  console.log('\n=== E2E Test Global Setup ===\n');

  // Wait for WordPress to be ready
  await waitForWordPress();

  // Verify plugin is active
  await verifyPluginActive();

  console.log('\n=== Setup Complete ===\n');
}
