/**
 * Subscribers Page Object
 *
 * Handles all interactions with the Subscribers page in WP-SMS dashboard.
 */

import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';
import { selectors } from '../helpers/selectors.js';

export class SubscribersPage extends BasePage {
  constructor(page) {
    super(page);
    this.tabName = 'subscribers';
  }

  /**
   * Navigate to the Subscribers page
   */
  async goto() {
    await super.goto(this.tabName);
  }

  // ==================== Quick Add Subscriber ====================

  /**
   * Add a new subscriber using the quick add form
   * @param {Object} subscriber - Subscriber data
   * @param {string} [subscriber.name] - Subscriber name (optional)
   * @param {string} subscriber.phone - Subscriber phone number
   */
  async quickAddSubscriber({ name, phone }) {
    // Fill name if provided
    if (name) {
      await this.page.fill(selectors.subscribers.quickAddName, name);
    }

    // Fill phone number (required)
    await this.page.fill(selectors.subscribers.quickAddPhone, phone);

    // Click add button
    await this.page.click(selectors.subscribers.addButton);

    // Wait for loading to complete
    await this.waitForLoadingComplete();
  }

  /**
   * Check if quick add form is visible
   * @returns {Promise<boolean>}
   */
  async isQuickAddVisible() {
    return await this.page.locator(selectors.subscribers.quickAddPhone).isVisible();
  }

  // ==================== Search and Filter ====================

  /**
   * Search for subscribers
   * @param {string} query - Search query
   */
  async search(query) {
    await this.page.fill(selectors.subscribers.searchInput, query);
    // Wait for debounce
    await this.page.waitForTimeout(600);
    await this.waitForLoadingComplete();
  }

  /**
   * Clear search input
   */
  async clearSearch() {
    await this.page.fill(selectors.subscribers.searchInput, '');
    await this.page.waitForTimeout(600);
    await this.waitForLoadingComplete();
  }

  /**
   * Filter by group
   * @param {string} groupName - Name of the group to filter by
   */
  async filterByGroup(groupName) {
    await this.page.click(selectors.subscribers.groupFilter);
    await this.page.click(`${selectors.form.selectOption}:has-text("${groupName}")`);
    await this.waitForLoadingComplete();
  }

  /**
   * Filter by status
   * @param {'active' | 'inactive' | 'all'} status - Status to filter by
   */
  async filterByStatus(status) {
    await this.page.click(selectors.subscribers.statusFilter);
    await this.page.click(`${selectors.form.selectOption}:has-text("${status}")`);
    await this.waitForLoadingComplete();
  }

  /**
   * Clear all filters
   */
  async clearFilters() {
    // Reset group filter to "All"
    await this.page.click(selectors.subscribers.groupFilter);
    await this.page.click(`${selectors.form.selectOption}:has-text("All")`);

    // Reset status filter to "All"
    await this.page.click(selectors.subscribers.statusFilter);
    await this.page.click(`${selectors.form.selectOption}:has-text("All")`);

    await this.waitForLoadingComplete();
  }

  // ==================== Edit Subscriber ====================

  /**
   * Open edit dialog for a subscriber
   * @param {number} rowIndex - Row index of the subscriber
   */
  async openEditDialog(rowIndex) {
    await this.clickRowAction(rowIndex, 'Edit');
    await this.waitForDialog();
  }

  /**
   * Update subscriber details in the edit dialog
   * @param {Object} details - Details to update
   * @param {string} [details.name] - New name
   * @param {string} [details.phone] - New phone number
   * @param {string} [details.group] - New group name
   * @param {'Active' | 'Inactive'} [details.status] - New status
   */
  async updateSubscriberDetails({ name, phone, group, status }) {
    if (name !== undefined) {
      await this.page.fill(`${selectors.dialog.container} input[placeholder*="name"]`, name);
    }

    if (phone !== undefined) {
      await this.page.fill(`${selectors.dialog.container} input[type="tel"]`, phone);
    }

    if (group !== undefined) {
      // Click group select
      await this.page.click(`${selectors.dialog.container} ${selectors.form.selectTrigger}:first-of-type`);
      await this.page.click(`${selectors.form.selectOption}:has-text("${group}")`);
    }

    if (status !== undefined) {
      // Click status select
      await this.page.click(`${selectors.dialog.container} ${selectors.form.selectTrigger}:last-of-type`);
      await this.page.click(`${selectors.form.selectOption}:has-text("${status}")`);
    }
  }

  /**
   * Save changes in the edit dialog
   */
  async saveSubscriber() {
    await this.page.click(`${selectors.dialog.container} >> button:has-text("Save")`);
    await this.page.waitForSelector(selectors.dialog.container, { state: 'hidden' });
    await this.waitForLoadingComplete();
  }

  /**
   * Edit a subscriber (complete flow)
   * @param {number} rowIndex - Row index
   * @param {Object} details - New details
   */
  async editSubscriber(rowIndex, details) {
    await this.openEditDialog(rowIndex);
    await this.updateSubscriberDetails(details);
    await this.saveSubscriber();
  }

  // ==================== Delete Subscriber ====================

  /**
   * Delete a subscriber
   * @param {number} rowIndex - Row index of the subscriber to delete
   */
  async deleteSubscriber(rowIndex) {
    await this.clickRowAction(rowIndex, 'Delete');
    await this.waitForLoadingComplete();
  }

  // ==================== Bulk Actions ====================

  /**
   * Select multiple subscribers
   * @param {number[]} indices - Array of row indices to select
   */
  async selectSubscribers(indices) {
    for (const index of indices) {
      await this.selectTableRow(index);
    }
  }

  /**
   * Activate selected subscribers
   */
  async bulkActivate() {
    await this.page.click('button:has-text("Activate")');
    await this.waitForLoadingComplete();
  }

  /**
   * Deactivate selected subscribers
   */
  async bulkDeactivate() {
    await this.page.click('button:has-text("Deactivate")');
    await this.waitForLoadingComplete();
  }

  /**
   * Delete selected subscribers
   */
  async bulkDelete() {
    await this.page.click('button:has-text("Delete")');
    await this.waitForLoadingComplete();
  }

  /**
   * Move selected subscribers to a group
   * @param {string} groupName - Name of the target group
   */
  async bulkMoveToGroup(groupName) {
    await this.page.click('button:has-text("Move to Group")');
    await this.waitForDialog();
    await this.page.click(`${selectors.dialog.container} ${selectors.form.selectTrigger}`);
    await this.page.click(`${selectors.form.selectOption}:has-text("${groupName}")`);
    await this.confirmDialog('Move');
    await this.waitForLoadingComplete();
  }

  // ==================== Import/Export ====================

  /**
   * Open the import dialog
   */
  async openImportDialog() {
    await this.page.click(selectors.subscribers.importButton);
    await this.waitForDialog();
  }

  /**
   * Import subscribers from a CSV file
   * @param {string} filePath - Path to the CSV file
   */
  async importCsv(filePath) {
    await this.openImportDialog();
    await this.page.setInputFiles('input[type="file"]', filePath);
    await this.page.click('button:has-text("Import")');
    await this.page.waitForSelector(selectors.dialog.container, { state: 'hidden' });
    await this.waitForLoadingComplete();
  }

  /**
   * Export subscribers to CSV
   * @returns {Promise<Download>} Download promise
   */
  async exportCsv() {
    const downloadPromise = this.page.waitForEvent('download');
    await this.page.click(selectors.subscribers.exportButton);
    return await downloadPromise;
  }

  // ==================== Quick Reply ====================

  /**
   * Send a quick reply to a subscriber
   * @param {number} rowIndex - Row index of the subscriber
   * @param {string} message - Message to send
   */
  async quickReply(rowIndex, message) {
    await this.clickRowAction(rowIndex, 'Quick Reply');
    await this.waitForDialog();
    await this.page.fill(`${selectors.dialog.container} textarea`, message);
    await this.page.click('button:has-text("Send")');
    await this.page.waitForSelector(selectors.dialog.container, { state: 'hidden' });
  }

  // ==================== Assertions ====================

  /**
   * Assert that a subscriber is visible in the table
   * @param {string} identifier - Phone number or name to search for
   */
  async expectSubscriberVisible(identifier) {
    await expect(this.page.locator(`${selectors.table.row}:has-text("${identifier}")`)).toBeVisible();
  }

  /**
   * Assert that a subscriber is not visible in the table
   * @param {string} identifier - Phone number or name to search for
   */
  async expectSubscriberNotVisible(identifier) {
    await expect(this.page.locator(`${selectors.table.row}:has-text("${identifier}")`)).not.toBeVisible();
  }

  /**
   * Assert empty state is shown
   */
  async expectEmptyState() {
    await expect(this.page.locator(selectors.table.emptyState)).toBeVisible();
  }

  /**
   * Assert table has specific number of rows
   * @param {number} count - Expected row count
   */
  async expectRowCount(count) {
    await expect(this.page.locator(selectors.table.row)).toHaveCount(count);
  }

  /**
   * Get subscriber data from a row
   * @param {number} rowIndex - Row index
   * @returns {Promise<Object>} Subscriber data
   */
  async getSubscriberData(rowIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    const cells = row.locator(selectors.table.cell);

    return {
      name: await cells.nth(1).textContent(),
      phone: await cells.nth(2).textContent(),
      group: await cells.nth(3).textContent(),
      status: await cells.nth(4).textContent(),
    };
  }
}
