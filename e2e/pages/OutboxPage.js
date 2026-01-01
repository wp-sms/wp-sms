/**
 * Outbox Page Object
 *
 * Handles all interactions with the Outbox page in WP-SMS dashboard.
 */

import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';
import { selectors } from '../helpers/selectors.js';

export class OutboxPage extends BasePage {
  constructor(page) {
    super(page);
    this.tabName = 'outbox';
  }

  /**
   * Navigate to the Outbox page
   */
  async goto() {
    await super.goto(this.tabName);
  }

  // ==================== Search and Filter ====================

  /**
   * Search for messages
   * @param {string} query - Search query
   */
  async search(query) {
    await this.page.fill(selectors.outbox.searchInput, query);
    await this.page.waitForTimeout(600); // Wait for debounce
    await this.waitForLoadingComplete();
  }

  /**
   * Clear search input
   */
  async clearSearch() {
    await this.page.fill(selectors.outbox.searchInput, '');
    await this.page.waitForTimeout(600);
    await this.waitForLoadingComplete();
  }

  /**
   * Filter by status
   * @param {'all' | 'sent' | 'failed'} status - Status to filter by
   */
  async filterByStatus(status) {
    await this.page.click(selectors.outbox.statusFilter);
    await this.page.click(`${selectors.form.selectOption}:has-text("${status}")`);
    await this.waitForLoadingComplete();
  }

  /**
   * Set date range filter
   * @param {string} fromDate - Start date (YYYY-MM-DD)
   * @param {string} toDate - End date (YYYY-MM-DD)
   */
  async filterByDateRange(fromDate, toDate) {
    if (fromDate) {
      await this.page.fill(selectors.outbox.dateFrom, fromDate);
    }
    if (toDate) {
      await this.page.fill(selectors.outbox.dateTo, toDate);
    }
    await this.waitForLoadingComplete();
  }

  /**
   * Clear all filters
   */
  async clearFilters() {
    await this.clearSearch();
    await this.filterByStatus('all');
    await this.page.fill(selectors.outbox.dateFrom, '');
    await this.page.fill(selectors.outbox.dateTo, '');
    await this.waitForLoadingComplete();
  }

  /**
   * Refresh the message list
   */
  async refresh() {
    await this.page.click(selectors.outbox.refreshButton);
    await this.waitForLoadingComplete();
  }

  // ==================== Message Actions ====================

  /**
   * View message details
   * @param {number} rowIndex - Row index of the message
   */
  async viewMessage(rowIndex) {
    await this.clickRowAction(rowIndex, 'View');
    await this.waitForDialog();
  }

  /**
   * Close the message details dialog
   */
  async closeMessageDetails() {
    await this.closeDialog();
  }

  /**
   * Resend a message
   * @param {number} rowIndex - Row index of the message
   */
  async resendMessage(rowIndex) {
    await this.clickRowAction(rowIndex, 'Resend');
    await this.waitForLoadingComplete();
  }

  /**
   * Delete a message
   * @param {number} rowIndex - Row index of the message
   */
  async deleteMessage(rowIndex) {
    await this.clickRowAction(rowIndex, 'Delete');
    await this.waitForLoadingComplete();
  }

  /**
   * Quick reply to a message sender
   * @param {number} rowIndex - Row index of the message
   * @param {string} replyMessage - Reply message text
   */
  async quickReply(rowIndex, replyMessage) {
    await this.clickRowAction(rowIndex, 'Quick Reply');
    await this.waitForDialog();
    await this.page.fill(`${selectors.dialog.container} textarea`, replyMessage);
    await this.page.click('button:has-text("Send")');
    await this.page.waitForSelector(selectors.dialog.container, { state: 'hidden' });
    await this.waitForLoadingComplete();
  }

  // ==================== Bulk Actions ====================

  /**
   * Select multiple messages
   * @param {number[]} indices - Array of row indices to select
   */
  async selectMessages(indices) {
    for (const index of indices) {
      await this.selectTableRow(index);
    }
  }

  /**
   * Bulk resend selected messages
   */
  async bulkResend() {
    await this.page.click('button:has-text("Resend")');
    await this.waitForLoadingComplete();
  }

  /**
   * Bulk delete selected messages
   */
  async bulkDelete() {
    await this.page.click('button:has-text("Delete")');
    await this.waitForLoadingComplete();
  }

  // ==================== Export ====================

  /**
   * Export messages to CSV
   * @returns {Promise<Download>} Download promise
   */
  async exportCsv() {
    const downloadPromise = this.page.waitForEvent('download');
    await this.page.click(selectors.outbox.exportButton);
    return await downloadPromise;
  }

  // ==================== Assertions ====================

  /**
   * Assert a message is visible in the table
   * @param {string} content - Message content or identifier
   */
  async expectMessageVisible(content) {
    await expect(this.page.locator(`${selectors.table.row}:has-text("${content}")`)).toBeVisible();
  }

  /**
   * Assert a message is not visible
   * @param {string} content - Message content or identifier
   */
  async expectMessageNotVisible(content) {
    await expect(this.page.locator(`${selectors.table.row}:has-text("${content}")`)).not.toBeVisible();
  }

  /**
   * Assert empty state is shown
   */
  async expectEmptyState() {
    await expect(this.page.locator(selectors.table.emptyState)).toBeVisible();
  }

  /**
   * Assert table has specific number of messages
   * @param {number} count - Expected message count
   */
  async expectMessageCount(count) {
    await expect(this.page.locator(selectors.table.row)).toHaveCount(count);
  }

  /**
   * Assert message details dialog shows correct content
   * @param {Object} expected - Expected message details
   * @param {string} [expected.recipient] - Expected recipient
   * @param {string} [expected.message] - Expected message content
   * @param {string} [expected.status] - Expected status
   */
  async expectMessageDetails({ recipient, message, status }) {
    const dialog = this.page.locator(selectors.dialog.container);
    await expect(dialog).toBeVisible();

    if (recipient) {
      await expect(dialog).toContainText(recipient);
    }
    if (message) {
      await expect(dialog).toContainText(message);
    }
    if (status) {
      await expect(dialog).toContainText(status);
    }
  }

  /**
   * Get message data from a row
   * @param {number} rowIndex - Row index
   * @returns {Promise<Object>} Message data
   */
  async getMessageData(rowIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    const cells = row.locator(selectors.table.cell);

    return {
      recipient: await cells.nth(1).textContent(),
      message: await cells.nth(2).textContent(),
      status: await cells.nth(3).textContent(),
      date: await cells.nth(4).textContent(),
    };
  }

  /**
   * Get message status badge color/type
   * @param {number} rowIndex - Row index
   * @returns {Promise<'success' | 'failed' | 'pending'>}
   */
  async getMessageStatus(rowIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    const badge = row.locator(selectors.status.badge);
    const classes = await badge.getAttribute('class');

    if (classes?.includes('success') || classes?.includes('green')) {
      return 'success';
    } else if (classes?.includes('destructive') || classes?.includes('red')) {
      return 'failed';
    } else {
      return 'pending';
    }
  }
}
