/**
 * Authentication Setup for Playwright E2E Tests
 *
 * Handles WordPress admin login and saves the session for reuse
 * across all tests.
 */

import { test as setup, expect } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const authFile = path.join(__dirname, '.auth/user.json');

setup('authenticate as admin', async ({ page }) => {
  const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8888';
  const autoLoginUrl = process.env.WP_AUTO_LOGIN_URL || '';
  const username = process.env.WP_ADMIN_USER || 'admin';
  const password = process.env.WP_ADMIN_PASSWORD || 'password';

  // Ensure .auth directory exists
  const authDir = path.dirname(authFile);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  // Use auto-login URL if provided (e.g., Local Sites)
  if (autoLoginUrl) {
    console.log(`Using auto-login URL: ${autoLoginUrl}`);
    await page.goto(autoLoginUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 30000 });
  } else {
    console.log(`Authenticating at ${baseUrl} as ${username}...`);

    // Navigate to login page
    await page.goto(`${baseUrl}/wp-login.php`);
    await page.waitForLoadState('networkidle');

    // Check if we're already logged in (redirected to admin)
    const isLoggedIn = await page.locator('#wpadminbar').isVisible().catch(() => false);

    if (!isLoggedIn) {
      // Check if we're on the login page
      const loginForm = await page.locator('#user_login').isVisible().catch(() => false);

      if (loginForm) {
        console.log('Login form detected, logging in...');
        await page.fill('#user_login', username);
        await page.fill('#user_pass', password);
        await page.click('#wp-submit');
        await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
      } else {
        // Navigate to admin
        await page.goto(`${baseUrl}/wp-admin/`);
        await page.waitForLoadState('networkidle');
      }
    }
  }

  console.log('Admin access verified!');

  // Navigate to WP-SMS Dashboard (React app)
  // Note: The React dashboard page slug is wp-sms-unified-admin
  await page.goto(`${baseUrl}/wp-admin/admin.php?page=wp-sms-unified-admin`);

  // Wait for React app to mount (with longer timeout for first load)
  await expect(page.locator('#wpsms-settings-root')).toBeVisible({ timeout: 30000 });

  console.log('WP-SMS settings page loaded!');

  // Save authentication state
  await page.context().storageState({ path: authFile });

  console.log(`Authentication state saved to ${authFile}`);
});
