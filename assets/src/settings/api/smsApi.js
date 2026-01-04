import { apiClient } from './client'

/**
 * SMS API methods
 * Handles sending SMS messages and related operations
 */
export const smsApi = {
  /**
   * Send SMS message using quick send endpoint
   * Supports immediate send, scheduled, and repeating messages
   * @param {object} data - SMS data
   * @param {string} data.message - Message content
   * @param {object} data.recipients - Recipients object
   * @param {string[]} data.recipients.groups - Group IDs
   * @param {string[]} data.recipients.roles - User role slugs
   * @param {string[]} data.recipients.numbers - Phone numbers
   * @param {string} data.from - Sender ID (optional)
   * @param {boolean} data.flash - Send as flash SMS
   * @param {string} data.mediaUrl - MMS media URL (optional)
   * @param {string} data.scheduled - Schedule date/time ISO string (optional, Pro feature)
   * @param {object} data.repeat - Repeat configuration (optional, Pro feature)
   * @param {number} data.repeat.interval - Repeat interval value
   * @param {string} data.repeat.unit - Repeat interval unit (day/week/month/year)
   * @param {string} data.repeat.endDate - End date ISO string (optional)
   * @param {boolean} data.repeat.forever - Whether to repeat forever
   * @returns {Promise<object>} Send result
   */
  async send(data) {
    const payload = {
      message: data.message,
      recipients: data.recipients,
      from: data.from || '',
      flash: data.flash || false,
      media_url: data.mediaUrl || '',
    }

    // Add scheduling parameters if provided (Pro feature)
    if (data.scheduled) {
      payload.schedule = data.scheduled
    }

    // Add repeat parameters if provided (Pro feature)
    if (data.repeat) {
      payload.repeat = {
        interval: data.repeat.interval || 1,
        unit: data.repeat.unit || 'day',
        endDate: data.repeat.forever ? null : (data.repeat.endDate || null),
      }
    }

    const response = await apiClient.post('send/quick', payload)
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
    try {
      const response = await apiClient.post('send/count', { recipients })
      return {
        total: response.data?.total || 0,
        groups: response.data?.groups || 0,
        roles: response.data?.roles || 0,
        numbers: response.data?.numbers || 0,
      }
    } catch (error) {
      // Fallback to local count if API fails
      const numbersCount = recipients.numbers?.length || 0
      const groupsCount = recipients.groups?.length || 0
      const rolesCount = recipients.roles?.length || 0
      return {
        total: numbersCount, // Can't count group/role members locally
        groups: groupsCount,
        roles: rolesCount,
        numbers: numbersCount,
      }
    }
  },

  /**
   * Get SMS credit balance
   * @returns {Promise<object>} Credit info with support status
   */
  async getCredit() {
    try {
      const response = await apiClient.get('credit')
      // The credit endpoint returns data directly, not nested in data
      return {
        credit: response.credit ?? response.data?.credit ?? null,
        creditSupported: response.creditSupported ?? response.data?.creditSupported ?? true,
        gateway: response.gateway ?? response.data?.gateway ?? null,
      }
    } catch (error) {
      console.error('Failed to get credit:', error)
      return { credit: null, creditSupported: false, gateway: null }
    }
  },

  /**
   * Validate phone numbers
   * @param {string[]} numbers - Phone numbers to validate
   * @returns {Promise<object>} Validation result
   */
  async validateNumbers(numbers) {
    try {
      const response = await apiClient.post('send/validate', { numbers })
      return {
        valid: response.data?.valid || [],
        invalid: response.data?.invalid || [],
      }
    } catch (error) {
      // Return all as valid if validation endpoint doesn't exist
      return {
        valid: numbers,
        invalid: [],
      }
    }
  },
}

export default smsApi
