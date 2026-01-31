/**
 * Extended Test Fixture for WP-SMS E2E Tests
 *
 * This fixture extends Playwright's base test with:
 * - Page object instances
 * - API mocking helpers
 * - Common test utilities
 */

import { test as base, expect } from '@playwright/test';
import { mockAllSmsApis, mockSendSmsApi, clearMocks } from '../helpers/api-mocks.js';
import { SubscribersPage } from '../pages/SubscribersPage.js';
import { GroupsPage } from '../pages/GroupsPage.js';
import { SendSmsPage } from '../pages/SendSmsPage.js';
import { OutboxPage } from '../pages/OutboxPage.js';

/**
 * Extended test fixture with page objects and helpers
 */
export const test = base.extend({
  /**
   * Subscribers page object
   */
  subscribersPage: async ({ page }, use) => {
    const subscribersPage = new SubscribersPage(page);
    await use(subscribersPage);
  },

  /**
   * Groups page object
   */
  groupsPage: async ({ page }, use) => {
    const groupsPage = new GroupsPage(page);
    await use(groupsPage);
  },

  /**
   * Send SMS page object
   */
  sendSmsPage: async ({ page }, use) => {
    const sendSmsPage = new SendSmsPage(page);
    await use(sendSmsPage);
  },

  /**
   * Outbox page object
   */
  outboxPage: async ({ page }, use) => {
    const outboxPage = new OutboxPage(page);
    await use(outboxPage);
  },

  /**
   * Helper to set up API mocks for SMS functionality
   * Usage: await mockSms('sendSuccess') or await mockSms({ custom: 'response' })
   */
  mockSms: async ({ page }, use) => {
    const mock = async (responseType = 'sendSuccess') => {
      await mockSendSmsApi(page, responseType);
    };
    await use(mock);
  },

  /**
   * Helper to set up all SMS API mocks with defaults
   */
  mockAllSms: async ({ page }, use) => {
    await mockAllSmsApis(page);
    await use();
  },

  /**
   * Helper to clear all mocks
   */
  clearMocks: async ({ page }, use) => {
    const clear = async () => {
      await clearMocks(page);
    };
    await use(clear);
  },

  /**
   * Navigate to a specific tab/page in WP-SMS settings
   * Note: The React settings page slug is wp-sms-unified-admin
   */
  navigateTo: async ({ page }, use) => {
    const navigate = async (tab) => {
      const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8888';
      await page.goto(`${baseUrl}/wp-admin/admin.php?page=wp-sms-unified-admin&tab=${tab}`);
      await page.waitForSelector('#wpsms-settings-root');
      // Wait for initial loading to complete
      await page.waitForTimeout(500);
    };
    await use(navigate);
  },

  /**
   * Wait for a notification toast to appear
   */
  waitForNotification: async ({ page }, use) => {
    const waitFor = async (type = 'success', timeout = 5000) => {
      const selector =
        type === 'success' ? '[role="alert"]:has([class*="success"], [class*="green"])' : '[role="alert"]:has([class*="destructive"], [class*="red"])';
      await page.waitForSelector(selector, { timeout });
    };
    await use(waitFor);
  },

  /**
   * Wait for table to finish loading
   */
  waitForTableLoad: async ({ page }, use) => {
    const waitFor = async () => {
      // Wait for any loading spinners to disappear
      await page.waitForSelector('[class*="animate-spin"]', { state: 'hidden' }).catch(() => {});
      // Small delay for data to render
      await page.waitForTimeout(300);
    };
    await use(waitFor);
  },
});

// Re-export expect for convenience
export { expect };

/**
 * Test tags for filtering
 */
export const tags = {
  smoke: '@smoke',
  subscribers: '@subscribers',
  groups: '@groups',
  sendSms: '@send-sms',
  outbox: '@outbox',
  crud: '@crud',
  bulk: '@bulk',
  filters: '@filters',
};
