/**
 * Send SMS Page Object
 *
 * Handles all interactions with the Send SMS page in WP-SMS dashboard.
 */

import { expect } from '@playwright/test';
import { BasePage } from './BasePage.js';
import { selectors } from '../helpers/selectors.js';

export class SendSmsPage extends BasePage {
  constructor(page) {
    super(page);
    this.tabName = 'send-sms';
  }

  /**
   * Navigate to the Send SMS page
   */
  async goto() {
    await super.goto(this.tabName);
  }

  // ==================== Message Composition ====================

  /**
   * Type a message in the composer
   * @param {string} message - Message text
   */
  async composeMessage(message) {
    await this.page.fill(selectors.sendSms.messageTextarea, message);
  }

  /**
   * Clear the message composer
   */
  async clearMessage() {
    await this.page.fill(selectors.sendSms.messageTextarea, '');
  }

  /**
   * Get the current message text
   * @returns {Promise<string>}
   */
  async getMessage() {
    return await this.page.inputValue(selectors.sendSms.messageTextarea);
  }

  /**
   * Get the character count display
   * @returns {Promise<string>}
   */
  async getCharCount() {
    return await this.page.locator(selectors.sendSms.charCount).textContent();
  }

  /**
   * Get the segment count display
   * @returns {Promise<string>}
   */
  async getSegmentCount() {
    return await this.page.locator(selectors.sendSms.segmentCount).textContent();
  }

  /**
   * Set the sender ID
   * @param {string} senderId - Sender ID
   */
  async setSenderId(senderId) {
    const senderInput = this.page.locator('input[placeholder*="Sender"]');
    if (await senderInput.isVisible()) {
      await senderInput.fill(senderId);
    }
  }

  /**
   * Toggle flash SMS option
   */
  async toggleFlashSms() {
    await this.page.click(selectors.sendSms.flashToggle);
  }

  /**
   * Set media URL for MMS
   * @param {string} url - Media URL
   */
  async setMediaUrl(url) {
    const mediaInput = this.page.locator(selectors.sendSms.mediaUrlInput);
    if (await mediaInput.isVisible()) {
      await mediaInput.fill(url);
    }
  }

  // ==================== Recipient Selection ====================

  /**
   * Switch to Groups tab in recipient selector
   */
  async selectGroupsTab() {
    await this.page.click(selectors.sendSms.tabGroups);
  }

  /**
   * Switch to Roles tab in recipient selector
   */
  async selectRolesTab() {
    await this.page.click(selectors.sendSms.tabRoles);
  }

  /**
   * Switch to Numbers tab in recipient selector
   */
  async selectNumbersTab() {
    await this.page.click(selectors.sendSms.tabNumbers);
  }

  /**
   * Select a group as recipient
   * @param {string} groupName - Name of the group
   */
  async selectGroup(groupName) {
    await this.selectGroupsTab();
    await this.page.click(`label:has-text("${groupName}") input[type="checkbox"]`);
  }

  /**
   * Deselect a group
   * @param {string} groupName - Name of the group
   */
  async deselectGroup(groupName) {
    await this.selectGroupsTab();
    const checkbox = this.page.locator(`label:has-text("${groupName}") input[type="checkbox"]`);
    if (await checkbox.isChecked()) {
      await checkbox.click();
    }
  }

  /**
   * Select a WordPress role as recipient
   * @param {string} roleName - Name of the role
   */
  async selectRole(roleName) {
    await this.selectRolesTab();
    await this.page.click(`label:has-text("${roleName}") input[type="checkbox"]`);
  }

  /**
   * Add a manual phone number
   * @param {string} phoneNumber - Phone number to add
   */
  async addManualNumber(phoneNumber) {
    await this.selectNumbersTab();
    await this.page.fill(selectors.sendSms.phoneInput, phoneNumber);
    await this.page.click(selectors.sendSms.addNumberButton);
  }

  /**
   * Add multiple phone numbers
   * @param {string[]} numbers - Array of phone numbers
   */
  async addMultipleNumbers(numbers) {
    for (const number of numbers) {
      await this.addManualNumber(number);
    }
  }

  /**
   * Remove a phone number chip
   * @param {string} phoneNumber - Phone number to remove
   */
  async removeNumber(phoneNumber) {
    await this.page.click(`${selectors.sendSms.numberChip}:has-text("${phoneNumber}") ${selectors.sendSms.removeNumber}`);
  }

  /**
   * Clear all manual numbers
   */
  async clearAllNumbers() {
    const removeButtons = this.page.locator(selectors.sendSms.removeNumber);
    while ((await removeButtons.count()) > 0) {
      await removeButtons.first().click();
    }
  }

  /**
   * Get the recipient count display
   * @returns {Promise<string>}
   */
  async getRecipientCount() {
    return await this.page.locator(selectors.sendSms.recipientCount).textContent();
  }

  /**
   * Get the credit balance display
   * @returns {Promise<string>}
   */
  async getCreditBalance() {
    return await this.page.locator(selectors.sendSms.creditBalance).textContent();
  }

  // ==================== Send Flow ====================

  /**
   * Click the preview/review button
   */
  async preview() {
    await this.page.click(selectors.sendSms.previewButton);
    await this.waitForDialog();
  }

  /**
   * Send the message (from preview dialog)
   */
  async confirmSend() {
    await this.page.click(`${selectors.dialog.container} >> button:has-text("Send")`);
    await this.waitForLoadingComplete();
  }

  /**
   * Send message directly (without preview)
   */
  async send() {
    await this.page.click(selectors.sendSms.sendButton);
    await this.waitForLoadingComplete();
  }

  /**
   * Complete send flow: compose, add recipients, preview, send
   * @param {Object} options - Send options
   * @param {string} options.message - Message text
   * @param {string[]} [options.groups] - Groups to send to
   * @param {string[]} [options.roles] - Roles to send to
   * @param {string[]} [options.numbers] - Phone numbers to send to
   */
  async sendMessage({ message, groups = [], roles = [], numbers = [] }) {
    // Compose message
    await this.composeMessage(message);

    // Select recipients
    for (const group of groups) {
      await this.selectGroup(group);
    }

    for (const role of roles) {
      await this.selectRole(role);
    }

    for (const number of numbers) {
      await this.addManualNumber(number);
    }

    // Preview and send
    await this.preview();
    await this.confirmSend();
  }

  // ==================== Assertions ====================

  /**
   * Assert message composer is visible
   */
  async expectComposerVisible() {
    await expect(this.page.locator(selectors.sendSms.messageTextarea)).toBeVisible();
  }

  /**
   * Assert character count shows specific value
   * @param {number} count - Expected character count
   */
  async expectCharCount(count) {
    await expect(this.page.locator(selectors.sendSms.charCount)).toContainText(String(count));
  }

  /**
   * Assert segment count shows specific value
   * @param {number} count - Expected segment count
   */
  async expectSegmentCount(count) {
    await expect(this.page.locator(selectors.sendSms.segmentCount)).toContainText(String(count));
  }

  /**
   * Assert send button is enabled
   */
  async expectSendEnabled() {
    await expect(this.page.locator(selectors.sendSms.sendButton)).toBeEnabled();
  }

  /**
   * Assert send button is disabled
   */
  async expectSendDisabled() {
    await expect(this.page.locator(selectors.sendSms.sendButton)).toBeDisabled();
  }

  /**
   * Assert recipient count shows specific value
   * @param {number} count - Expected recipient count
   */
  async expectRecipientCount(count) {
    await expect(this.page.locator(selectors.sendSms.recipientCount)).toContainText(String(count));
  }

  /**
   * Assert a number chip is visible
   * @param {string} phoneNumber - Phone number
   */
  async expectNumberChipVisible(phoneNumber) {
    await expect(this.page.locator(`${selectors.sendSms.numberChip}:has-text("${phoneNumber}")`)).toBeVisible();
  }
}
