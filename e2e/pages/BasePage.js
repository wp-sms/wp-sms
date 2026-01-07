/**
 * Base Page Object for WP-SMS Dashboard
 *
 * Contains common methods shared across all page objects.
 */

import { expect } from '@playwright/test';
import { selectors } from '../helpers/selectors.js';

export class BasePage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page;
    this.baseUrl = process.env.WP_BASE_URL || 'http://localhost:8888';
    // The React settings page slug is wp-sms-unified-admin
    this.settingsUrl = `${this.baseUrl}/wp-admin/admin.php?page=wp-sms-unified-admin`;
  }

  /**
   * Navigate to the settings page with optional tab
   * @param {string} tab - Tab name (e.g., 'subscribers', 'groups')
   */
  async goto(tab = '') {
    const url = tab ? `${this.settingsUrl}&tab=${tab}` : this.settingsUrl;
    await this.page.goto(url);
    await this.waitForPageLoad();
  }

  /**
   * Wait for the React app to fully load
   */
  async waitForPageLoad() {
    // Wait for React app root
    await this.page.waitForSelector(selectors.app.root, { timeout: 15000 });

    // Wait for loading states to finish
    await this.waitForLoadingComplete();
  }

  /**
   * Wait for all loading states to complete
   */
  async waitForLoadingComplete() {
    // Wait for spinners to disappear
    await this.page.waitForSelector(selectors.loading.spinner, { state: 'hidden' }).catch(() => {});

    // Wait for skeletons to disappear
    await this.page.waitForSelector(selectors.loading.skeleton, { state: 'hidden' }).catch(() => {});

    // Small delay for UI to stabilize
    await this.page.waitForTimeout(200);
  }

  /**
   * Navigate to a different tab/page
   * @param {string} tabName - Name of the tab to navigate to
   */
  async navigateTo(tabName) {
    const navSelector = selectors.nav.item(tabName);
    await this.page.click(navSelector);
    await this.waitForPageLoad();
  }

  /**
   * Get the current tab/page from URL
   * @returns {string|null} Current tab name
   */
  async getCurrentTab() {
    const url = this.page.url();
    const match = url.match(/tab=([^&]+)/);
    return match ? match[1] : null;
  }

  // ==================== Table Methods ====================

  /**
   * Get the number of rows in the data table
   * @returns {Promise<number>}
   */
  async getTableRowCount() {
    await this.waitForLoadingComplete();
    return await this.page.locator(selectors.table.row).count();
  }

  /**
   * Check if table is empty (shows empty state)
   * @returns {Promise<boolean>}
   */
  async isTableEmpty() {
    const emptyState = this.page.locator(selectors.table.emptyState);
    return await emptyState.isVisible().catch(() => false);
  }

  /**
   * Select a table row by index
   * @param {number} index - Row index (0-based)
   */
  async selectTableRow(index) {
    const rows = this.page.locator(selectors.table.row);
    await rows.nth(index).locator(selectors.table.checkbox).click();
  }

  /**
   * Select all rows in the table
   */
  async selectAllRows() {
    await this.page.click(selectors.table.selectAll);
  }

  /**
   * Get text content of a specific cell
   * @param {number} rowIndex - Row index
   * @param {number} colIndex - Column index
   * @returns {Promise<string>}
   */
  async getCellText(rowIndex, colIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    const cell = row.locator(selectors.table.cell).nth(colIndex);
    return await cell.textContent();
  }

  // ==================== Action Menu Methods ====================

  /**
   * Open the action menu for a specific row
   * @param {number} rowIndex - Row index
   */
  async openRowActionMenu(rowIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    await row.locator(selectors.actionMenu.trigger).click();
    await this.page.waitForSelector(selectors.actionMenu.content);
  }

  /**
   * Click an action in the row's action menu
   * @param {number} rowIndex - Row index
   * @param {string} actionText - Text of the action (e.g., 'Edit', 'Delete')
   */
  async clickRowAction(rowIndex, actionText) {
    await this.openRowActionMenu(rowIndex);
    await this.page.click(`${selectors.actionMenu.content} >> text="${actionText}"`);
  }

  // ==================== Dialog Methods ====================

  /**
   * Wait for a dialog to appear
   */
  async waitForDialog() {
    await this.page.waitForSelector(selectors.dialog.container);
  }

  /**
   * Close the currently open dialog
   */
  async closeDialog() {
    await this.page.click(selectors.dialog.close);
    await this.page.waitForSelector(selectors.dialog.container, { state: 'hidden' });
  }

  /**
   * Confirm a dialog (click the confirm/submit button)
   * @param {string} buttonText - Text of the confirm button
   */
  async confirmDialog(buttonText = 'Confirm') {
    await this.page.click(`${selectors.dialog.container} >> button:has-text("${buttonText}")`);
    await this.page.waitForSelector(selectors.dialog.container, { state: 'hidden' }).catch(() => {});
  }

  /**
   * Get the dialog title text
   * @returns {Promise<string>}
   */
  async getDialogTitle() {
    return await this.page.locator(selectors.dialog.title).textContent();
  }

  // ==================== Notification Methods ====================

  /**
   * Wait for a notification toast to appear
   * @param {'success' | 'error'} type - Type of notification
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForNotification(type = 'success', timeout = 5000) {
    const selector = type === 'success' ? selectors.notification.success : selectors.notification.error;
    await this.page.waitForSelector(selector, { timeout });
  }

  /**
   * Expect a success notification with specific text
   * @param {string} text - Text to expect in the notification
   */
  async expectSuccessNotification(text) {
    const notification = this.page.locator(selectors.notification.container);
    await expect(notification).toBeVisible();
    if (text) {
      await expect(notification).toContainText(text);
    }
  }

  /**
   * Expect an error notification with specific text
   * @param {string} text - Text to expect in the notification
   */
  async expectErrorNotification(text) {
    const notification = this.page.locator(selectors.notification.error);
    await expect(notification).toBeVisible();
    if (text) {
      await expect(notification).toContainText(text);
    }
  }

  // ==================== Pagination Methods ====================

  /**
   * Go to the next page in pagination
   */
  async goToNextPage() {
    await this.page.click(selectors.pagination.next);
    await this.waitForLoadingComplete();
  }

  /**
   * Go to the previous page in pagination
   */
  async goToPreviousPage() {
    await this.page.click(selectors.pagination.prev);
    await this.waitForLoadingComplete();
  }

  /**
   * Check if next page button is enabled
   * @returns {Promise<boolean>}
   */
  async hasNextPage() {
    const nextButton = this.page.locator(selectors.pagination.next);
    return await nextButton.isEnabled();
  }

  /**
   * Check if previous page button is enabled
   * @returns {Promise<boolean>}
   */
  async hasPreviousPage() {
    const prevButton = this.page.locator(selectors.pagination.prev);
    return await prevButton.isEnabled();
  }

  // ==================== Utility Methods ====================

  /**
   * Fill a form field
   * @param {string} selector - Field selector
   * @param {string} value - Value to fill
   */
  async fillField(selector, value) {
    await this.page.fill(selector, value);
  }

  /**
   * Clear and fill a form field
   * @param {string} selector - Field selector
   * @param {string} value - Value to fill
   */
  async clearAndFill(selector, value) {
    await this.page.fill(selector, '');
    await this.page.fill(selector, value);
  }

  /**
   * Click a button by text
   * @param {string} text - Button text
   */
  async clickButton(text) {
    await this.page.click(`button:has-text("${text}")`);
  }

  /**
   * Check if an element is visible
   * @param {string} selector - Element selector
   * @returns {Promise<boolean>}
   */
  async isVisible(selector) {
    return await this.page.locator(selector).isVisible();
  }

  /**
   * Take a screenshot for debugging
   * @param {string} name - Screenshot name
   */
  async screenshot(name) {
    await this.page.screenshot({ path: `e2e-results/${name}.png` });
  }
}
