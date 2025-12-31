import { apiClient } from './client'

/**
 * SMS API methods
 * Handles sending SMS messages and related operations
 */
export const smsApi = {
  /**
   * Send SMS message
   * @param {object} data - SMS data
   * @param {string} data.message - Message content
   * @param {object} data.recipients - Recipients object
   * @param {string[]} data.recipients.groups - Group IDs
   * @param {string[]} data.recipients.roles - User role slugs
   * @param {string[]} data.recipients.numbers - Phone numbers
   * @param {boolean} data.flash - Send as flash SMS
   * @param {string} data.mediaUrl - MMS media URL (optional)
   * @returns {Promise<object>} Send result
   */
  async send(data) {
    const response = await apiClient.post('send', {
      message: data.message,
      recipients: data.recipients,
      flash: data.flash || false,
      media_url: data.mediaUrl || '',
    })
    return {
      success: true,
      message: response.message,
      recipientCount: response.data?.recipient_count,
      credit: response.data?.credit,
    }
  },

  /**
   * Get recipient count preview
   * @param {object} recipients - Recipients object
   * @param {string[]} recipients.groups - Group IDs
   * @param {string[]} recipients.roles - User role slugs
   * @param {string[]} recipients.numbers - Phone numbers
   * @returns {Promise<object>} Recipient count
   */
  async getRecipientCount(recipients) {
    const response = await apiClient.post('send/count', { recipients })
    return {
      total: response.data?.total || 0,
      groups: response.data?.groups || 0,
      roles: response.data?.roles || 0,
      numbers: response.data?.numbers || 0,
    }
  },

  /**
   * Get SMS credit balance
   * @returns {Promise<object>} Credit info
   */
  async getCredit() {
    const response = await apiClient.get('credit')
    return {
      credit: response.data?.credit,
      gateway: response.data?.gateway,
    }
  },

  /**
   * Validate phone numbers
   * @param {string[]} numbers - Phone numbers to validate
   * @returns {Promise<object>} Validation result
   */
  async validateNumbers(numbers) {
    const response = await apiClient.post('send/validate', { numbers })
    return {
      valid: response.data?.valid || [],
      invalid: response.data?.invalid || [],
    }
  },
}

export default smsApi
