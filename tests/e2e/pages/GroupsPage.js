/**
 * Groups Page Object
 *
 * Handles all interactions with the Groups page in WP-SMS dashboard.
 */

import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';
import { selectors } from '../helpers/selectors.js';

export class GroupsPage extends BasePage {
  constructor(page) {
    super(page);
    this.tabName = 'groups';
  }

  /**
   * Navigate to the Groups page
   */
  async goto() {
    await super.goto(this.tabName);
  }

  // ==================== Quick Add Group ====================

  /**
   * Add a new group using the quick add form
   * @param {string} name - Group name
   */
  async quickAddGroup(name) {
    await this.page.fill(selectors.groups.quickAddInput, name);
    await this.page.click(selectors.groups.createButton);
    await this.waitForLoadingComplete();
  }

  /**
   * Check if quick add form is visible
   * @returns {Promise<boolean>}
   */
  async isQuickAddVisible() {
    return await this.page.locator(selectors.groups.quickAddInput).isVisible();
  }

  // ==================== View Toggle ====================

  /**
   * Switch to list view
   */
  async switchToListView() {
    await this.page.click(selectors.groups.viewToggle.list);
    await this.waitForLoadingComplete();
  }

  /**
   * Switch to grid view
   */
  async switchToGridView() {
    await this.page.click(selectors.groups.viewToggle.grid);
    await this.waitForLoadingComplete();
  }

  /**
   * Check if currently in list view
   * @returns {Promise<boolean>}
   */
  async isListView() {
    const listButton = this.page.locator(selectors.groups.viewToggle.list);
    const classes = await listButton.getAttribute('class');
    return classes?.includes('active') || classes?.includes('selected') || false;
  }

  /**
   * Check if currently in grid view
   * @returns {Promise<boolean>}
   */
  async isGridView() {
    const gridCards = this.page.locator(selectors.groups.gridCard);
    return (await gridCards.count()) > 0;
  }

  // ==================== Inline Edit (List View) ====================

  /**
   * Start inline editing for a group in list view
   * @param {number} rowIndex - Row index of the group
   */
  async startInlineEdit(rowIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    // Double-click or click edit button to start inline edit
    await row.locator('button:has-text("Edit")').click().catch(async () => {
      // If no edit button, try double-clicking the name cell
      await row.locator(selectors.table.cell).nth(1).dblclick();
    });
    await this.page.waitForSelector(selectors.groups.inlineEditInput);
  }

  /**
   * Complete inline edit with new name
   * @param {string} newName - New group name
   */
  async completeInlineEdit(newName) {
    const input = this.page.locator(selectors.groups.inlineEditInput);
    await input.fill('');
    await input.fill(newName);
    await input.press('Enter');
    await this.waitForLoadingComplete();
  }

  /**
   * Cancel inline edit
   */
  async cancelInlineEdit() {
    await this.page.keyboard.press('Escape');
  }

  /**
   * Edit a group name (complete flow in list view)
   * @param {number} rowIndex - Row index
   * @param {string} newName - New group name
   */
  async editGroupName(rowIndex, newName) {
    await this.startInlineEdit(rowIndex);
    await this.completeInlineEdit(newName);
  }

  // ==================== Grid View Operations ====================

  /**
   * Get all group cards in grid view
   * @returns {Promise<Locator>}
   */
  getGridCards() {
    return this.page.locator(selectors.groups.gridCard);
  }

  /**
   * Click edit on a grid card
   * @param {number} cardIndex - Card index
   */
  async editGridCard(cardIndex) {
    const card = this.page.locator(selectors.groups.gridCard).nth(cardIndex);
    await card.hover();
    await card.locator('button:has-text("Edit")').click();
    await this.page.waitForSelector(selectors.groups.inlineEditInput);
  }

  /**
   * Delete a group from grid view
   * @param {number} cardIndex - Card index
   */
  async deleteFromGrid(cardIndex) {
    const card = this.page.locator(selectors.groups.gridCard).nth(cardIndex);
    await card.hover();
    await card.locator('button:has-text("Delete")').click();
    await this.waitForLoadingComplete();
  }

  // ==================== Delete Group ====================

  /**
   * Delete a group from list view
   * @param {number} rowIndex - Row index of the group to delete
   */
  async deleteGroup(rowIndex) {
    await this.clickRowAction(rowIndex, 'Delete');
    // If there's a confirmation dialog
    await this.confirmDialog('Delete').catch(() => {});
    await this.waitForLoadingComplete();
  }

  // ==================== Assertions ====================

  /**
   * Assert that a group is visible in the list/grid
   * @param {string} name - Group name to search for
   */
  async expectGroupVisible(name) {
    await expect(this.page.locator(`text="${name}"`)).toBeVisible();
  }

  /**
   * Assert that a group is not visible
   * @param {string} name - Group name to search for
   */
  async expectGroupNotVisible(name) {
    await expect(this.page.locator(`text="${name}"`)).not.toBeVisible();
  }

  /**
   * Assert empty state is shown
   */
  async expectEmptyState() {
    await expect(this.page.locator(selectors.table.emptyState)).toBeVisible();
  }

  /**
   * Assert table has specific number of groups
   * @param {number} count - Expected group count
   */
  async expectGroupCount(count) {
    await expect(this.page.locator(selectors.table.row)).toHaveCount(count);
  }

  /**
   * Assert grid has specific number of cards
   * @param {number} count - Expected card count
   */
  async expectGridCardCount(count) {
    await expect(this.page.locator(selectors.groups.gridCard)).toHaveCount(count);
  }

  /**
   * Get group data from a row
   * @param {number} rowIndex - Row index
   * @returns {Promise<Object>} Group data
   */
  async getGroupData(rowIndex) {
    const row = this.page.locator(selectors.table.row).nth(rowIndex);
    const cells = row.locator(selectors.table.cell);

    return {
      name: await cells.nth(1).textContent(),
      subscriberCount: await cells.nth(2).textContent(),
    };
  }

  /**
   * Get group data from a grid card
   * @param {number} cardIndex - Card index
   * @returns {Promise<Object>} Group data
   */
  async getGridCardData(cardIndex) {
    const card = this.page.locator(selectors.groups.gridCard).nth(cardIndex);

    return {
      name: await card.locator('h3, [class*="title"]').textContent(),
      subscriberCount: await card.locator('[class*="count"], [class*="badge"]').textContent(),
    };
  }
}
