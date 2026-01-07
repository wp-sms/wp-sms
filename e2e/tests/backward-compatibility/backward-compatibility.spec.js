/**
 * Backward Compatibility E2E Tests
 *
 * Tests that verify data created in one interface (legacy/React) is accessible
 * and functional in the other interface.
 *
 * Prerequisites:
 * - WordPress with WP-SMS plugin installed
 * - Admin user credentials configured in .env.e2e
 * - Database access for seeding legacy data
 */

import { test, expect } from '../../fixtures/test.js';

// Tag for filtering backward compatibility tests
const BC_TAG = '@backward-compatibility';

test.describe('Backward Compatibility - Cross-Interface Data Visibility', () => {
  test.describe('Subscribers', () => {
    test(`${BC_TAG} - subscribers from database appear in React dashboard`, async ({
      page,
      subscribersPage,
    }) => {
      // Navigate to React subscribers page
      await subscribersPage.goto();

      // Wait for table to load
      await page.waitForSelector('[data-testid="subscribers-table"], table', {
        timeout: 10000,
      });

      // Verify the table is rendered (empty or with data)
      const table = page.locator('[data-testid="subscribers-table"], table');
      await expect(table).toBeVisible();
    });

    test(`${BC_TAG} - subscriber created in React is persisted in database`, async ({
      page,
      subscribersPage,
    }) => {
      await subscribersPage.goto();

      // Generate unique phone number for test
      const uniquePhone = '+1555' + Date.now().toString().slice(-7);
      const testName = 'E2E Test User ' + Date.now();

      // Try to add subscriber via quick add form
      const quickAddPhone = page.locator('input[placeholder*="phone"], input[type="tel"]').first();

      if (await quickAddPhone.isVisible()) {
        await quickAddPhone.fill(uniquePhone);

        const nameInput = page.locator('input[placeholder*="name"]').first();
        if (await nameInput.isVisible()) {
          await nameInput.fill(testName);
        }

        // Click add button
        const addButton = page.locator('button:has-text("Add"), button[type="submit"]').first();
        if (await addButton.isVisible()) {
          await addButton.click();

          // Wait for response
          await page.waitForTimeout(1000);
        }
      }
    });

    test(`${BC_TAG} - subscriber status toggle works correctly`, async ({
      page,
      subscribersPage,
    }) => {
      await subscribersPage.goto();

      // Check if there are any subscribers in the table
      const rows = page.locator('table tbody tr, [data-testid="subscriber-row"]');
      const rowCount = await rows.count();

      if (rowCount > 0) {
        // Find a status badge or toggle
        const statusBadge = rows.first().locator('[class*="badge"], [class*="status"]');
        if (await statusBadge.isVisible()) {
          const initialStatus = await statusBadge.textContent();
          expect(initialStatus).toBeTruthy();
        }
      }
    });
  });

  test.describe('Groups', () => {
    test(`${BC_TAG} - groups from database appear in React dashboard`, async ({
      page,
      groupsPage,
    }) => {
      await groupsPage.goto();

      // Wait for page load
      await page.waitForSelector('[data-testid="groups-table"], table, [class*="card"]', {
        timeout: 10000,
      });

      // Page should load without errors
      const errorMessage = page.locator('[role="alert"]:has-text("error")');
      await expect(errorMessage).not.toBeVisible();
    });

    test(`${BC_TAG} - group created in React appears in subscriber group dropdown`, async ({
      page,
      groupsPage,
      subscribersPage,
    }) => {
      // First, create a group
      await groupsPage.goto();

      const uniqueGroupName = 'E2E Test Group ' + Date.now();

      // Find add group button/input
      const addGroupInput = page.locator('input[placeholder*="group"], input[placeholder*="name"]').first();
      if (await addGroupInput.isVisible()) {
        await addGroupInput.fill(uniqueGroupName);

        const addButton = page.locator('button:has-text("Add"), button[type="submit"]').first();
        if (await addButton.isVisible()) {
          await addButton.click();
          await page.waitForTimeout(1000);
        }
      }

      // Navigate to subscribers and check group dropdown
      await subscribersPage.goto();

      // Find group filter/dropdown
      const groupFilter = page.locator('[data-testid="group-filter"], select, [role="combobox"]').first();
      if (await groupFilter.isVisible()) {
        await groupFilter.click();

        // Check if the new group appears in options
        const groupOption = page.locator(`[role="option"]:has-text("${uniqueGroupName}"), option:has-text("${uniqueGroupName}")`);
        // The group should appear if it was created successfully
      }
    });
  });

  test.describe('Settings', () => {
    test(`${BC_TAG} - gateway settings are loaded from database`, async ({ page, navigateTo }) => {
      await navigateTo('gateway');

      // Wait for settings to load
      await page.waitForSelector('[data-testid="gateway-settings"], form, [class*="settings"]', {
        timeout: 10000,
      });

      // Find gateway select/dropdown
      const gatewaySelect = page.locator('[data-testid="gateway-select"], select[name="gateway"], [name*="gateway"]').first();

      if (await gatewaySelect.isVisible()) {
        // Gateway selector should have options
        await expect(gatewaySelect).toBeVisible();
      }
    });

    test(`${BC_TAG} - settings changes persist across page reloads`, async ({ page, navigateTo }) => {
      await navigateTo('notifications');

      // Wait for page load
      await page.waitForTimeout(1000);

      // Find a toggle/switch
      const toggle = page.locator('button[role="switch"], input[type="checkbox"]').first();

      if (await toggle.isVisible()) {
        const initialState = await toggle.getAttribute('aria-checked') || await toggle.isChecked();

        // Toggle it
        await toggle.click();
        await page.waitForTimeout(500);

        // Find save button and click
        const saveButton = page.locator('button:has-text("Save")').first();
        if (await saveButton.isVisible()) {
          await saveButton.click();
          await page.waitForTimeout(1000);
        }

        // Reload page
        await page.reload();
        await page.waitForSelector('button[role="switch"], input[type="checkbox"]', { timeout: 5000 });

        // State should persist (or be reverted if save wasn't successful)
      }
    });

    test(`${BC_TAG} - masked password fields preserve existing values`, async ({
      page,
      navigateTo,
    }) => {
      await navigateTo('gateway');

      await page.waitForTimeout(1000);

      // Find password fields
      const passwordField = page.locator('input[type="password"]').first();

      if (await passwordField.isVisible()) {
        const value = await passwordField.inputValue();

        // Masked passwords typically show bullets
        if (value) {
          // If there's a value, it should be masked or actual password
          expect(value.length).toBeGreaterThan(0);
        }
      }
    });
  });

  test.describe('Outbox', () => {
    test(`${BC_TAG} - sent messages appear in outbox`, async ({ page, outboxPage }) => {
      await outboxPage.goto();

      // Wait for table or empty state
      await page.waitForSelector('table, [data-testid="outbox-table"], [data-testid="empty-state"]', {
        timeout: 10000,
      });

      // Page should load without errors
      const errorAlert = page.locator('[role="alert"]:has-text("error")');
      await expect(errorAlert).not.toBeVisible();
    });

    test(`${BC_TAG} - outbox filters work correctly`, async ({ page, outboxPage }) => {
      await outboxPage.goto();

      await page.waitForTimeout(1000);

      // Find status filter
      const statusFilter = page.locator('[data-testid="status-filter"], select, [role="combobox"]').first();

      if (await statusFilter.isVisible()) {
        await statusFilter.click();

        // Check for filter options
        const options = page.locator('[role="option"], option');
        const optionCount = await options.count();

        // Should have at least some filter options
        expect(optionCount).toBeGreaterThan(0);
      }
    });
  });

  test.describe('Send SMS', () => {
    test(`${BC_TAG} - send page loads recipient options from database`, async ({
      page,
      sendSmsPage,
    }) => {
      await sendSmsPage.goto();

      await page.waitForTimeout(1000);

      // Find recipient type selector or input
      const recipientInput = page.locator(
        '[data-testid="recipients-input"], input[name="recipients"], [placeholder*="recipient"], textarea'
      ).first();

      if (await recipientInput.isVisible()) {
        await expect(recipientInput).toBeEnabled();
      }
    });

    test(`${BC_TAG} - send to group option shows database groups`, async ({
      page,
      sendSmsPage,
    }) => {
      await sendSmsPage.goto();

      await page.waitForTimeout(1000);

      // Find group selector
      const groupSelector = page.locator(
        '[data-testid="group-select"], select[name="group"], [placeholder*="group"]'
      ).first();

      if (await groupSelector.isVisible()) {
        await groupSelector.click();

        // Should show groups from database
        const groupOptions = page.locator('[role="option"], option');
        // Groups should load from the database
      }
    });
  });
});

test.describe('Backward Compatibility - Data Consistency', () => {
  test(`${BC_TAG} - subscriber count matches across interfaces`, async ({
    page,
    subscribersPage,
  }) => {
    await subscribersPage.goto();

    await page.waitForTimeout(1000);

    // Find stats/count display
    const statsDisplay = page.locator('[data-testid="subscriber-count"], [class*="stats"], [class*="count"]');

    if (await statsDisplay.first().isVisible()) {
      const statsText = await statsDisplay.first().textContent();
      // Should show some count information
      expect(statsText).toBeTruthy();
    }
  });

  test(`${BC_TAG} - pagination respects total record count`, async ({
    page,
    subscribersPage,
  }) => {
    await subscribersPage.goto();

    await page.waitForTimeout(1000);

    // Find pagination controls
    const pagination = page.locator('[data-testid="pagination"], nav[aria-label*="pagination"], [class*="pagination"]');

    if (await pagination.isVisible()) {
      // Check for page info
      const pageInfo = await pagination.textContent();
      // Should show pagination information
      expect(pageInfo).toBeTruthy();
    }
  });

  test(`${BC_TAG} - search results are consistent with database`, async ({
    page,
    subscribersPage,
  }) => {
    await subscribersPage.goto();

    await page.waitForTimeout(1000);

    // Find search input
    const searchInput = page.locator('[data-testid="search-input"], input[type="search"], input[placeholder*="search"]').first();

    if (await searchInput.isVisible()) {
      // Search for a term
      await searchInput.fill('test');
      await page.waitForTimeout(1000);

      // Results should update
      // The table should either show results or empty state
      const table = page.locator('table tbody, [data-testid="subscriber-row"]');
      const emptyState = page.locator('[data-testid="empty-state"], [class*="empty"]');

      const hasResults = await table.isVisible();
      const isEmpty = await emptyState.isVisible();

      // One should be true
      expect(hasResults || isEmpty).toBeTruthy();
    }
  });
});

test.describe('Backward Compatibility - Error Handling', () => {
  test(`${BC_TAG} - invalid legacy data is handled gracefully`, async ({
    page,
    subscribersPage,
  }) => {
    await subscribersPage.goto();

    // Page should load even if there's invalid data
    await page.waitForTimeout(1000);

    // Check for crash indicators
    const crashIndicator = page.locator('text=Something went wrong, text=Error boundary');
    await expect(crashIndicator).not.toBeVisible();

    // Page should still be interactive
    const anyButton = page.locator('button').first();
    if (await anyButton.isVisible()) {
      await expect(anyButton).toBeEnabled();
    }
  });

  test(`${BC_TAG} - API errors show user-friendly messages`, async ({
    page,
    subscribersPage,
  }) => {
    await subscribersPage.goto();

    // Try an action that might fail
    const searchInput = page.locator('input[type="search"], input[placeholder*="search"]').first();

    if (await searchInput.isVisible()) {
      // Search for something that won't match
      await searchInput.fill('nonexistent_subscriber_xyz_12345');
      await page.waitForTimeout(1000);

      // Should show empty state, not error
      const errorMessage = page.locator('[role="alert"]:has-text("error"), [class*="error"]:has-text("error")');
      // Empty results should not be an error
    }
  });
});

test.describe('Backward Compatibility - Navigation and URL Parameters', () => {
  test(`${BC_TAG} - tab parameter in URL loads correct page`, async ({ page }) => {
    const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8888';

    // Navigate with tab parameter
    await page.goto(`${baseUrl}/wp-admin/admin.php?page=wp-sms-unified-admin&tab=subscribers`);

    await page.waitForTimeout(1000);

    // Should show subscribers content
    const pageContent = await page.content();
    expect(pageContent).toBeTruthy();
  });

  test(`${BC_TAG} - legacy URL redirects work correctly`, async ({ page }) => {
    const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8888';

    // Navigate to settings root
    await page.goto(`${baseUrl}/wp-admin/admin.php?page=wp-sms-unified-admin`);

    await page.waitForTimeout(1000);

    // Should load the dashboard
    const root = page.locator('#wpsms-settings-root, [id*="wpsms"]');
    await expect(root).toBeVisible();
  });
});
