import { getWpSettings, __ } from '../lib/utils'
import apiClient from './client'

/**
 * Wizard API methods
 */
export const wizardApi = {
  /**
   * Test gateway connection status
   * Calls the legacy AJAX endpoint with credentials
   * @param {object} credentials - Gateway credential values
   * @returns {Promise<object>} Gateway test result
   */
  async testGatewayStatus(credentials = {}) {
    const settings = getWpSettings()
    // Fallback to default WordPress AJAX URL if not provided
    const ajaxUrl = settings.ajaxUrl || `${settings.adminUrl || '/wp-admin/'}admin-ajax.php`
    const ajaxNonce = settings.ajaxNonce || ''

    const formData = new FormData()
    formData.append('action', 'wp_sms_test_gateway')
    formData.append('sub_action', 'test_status')
    formData.append('_nonce', ajaxNonce)

    // Add credential fields
    Object.entries(credentials).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        formData.append(key, value)
      }
    })

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
    })

    // Check if response is JSON
    const contentType = response.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text()
      console.error('Non-JSON response:', text.substring(0, 200))
      throw new Error('Server returned an invalid response. Please try again.')
    }

    const data = await response.json()

    if (!data.success) {
      throw new Error(data.data?.message || 'Failed to test gateway')
    }

    return {
      success: true,
      status: data.data.status,
      balance: data.data.balance,
      incoming: data.data.incoming,
      bulk: data.data.bulk,
      mms: data.data.mms,
      isActive: data.data.status?.class?.includes('success'),
    }
  },

  /**
   * Send test SMS to admin number
   * Uses the existing /send/quick REST API endpoint
   * @param {string} phoneNumber - The phone number to send test SMS to
   * @returns {Promise<object>} Send result
   */
  async sendTestSms(phoneNumber) {
    const settings = getWpSettings()
    const adminNumber = phoneNumber || settings?.settings?.admin_mobile_number

    if (!adminNumber) {
      throw new Error('No phone number configured')
    }

    const response = await apiClient.post('send/quick', {
      recipients: {
        numbers: [adminNumber],
      },
      message: __('This is a test message from WP SMS setup wizard.'),
    })

    return {
      success: true,
      sent: true,
      to: adminNumber,
      message: response.message,
    }
  },

  /**
   * Mark wizard as completed
   * @returns {Promise<object>} Result
   */
  async markWizardComplete() {
    const response = await apiClient.post('wizard/complete', {})
    return response
  },

  /**
   * Get wizard status
   * @returns {Promise<object>} Wizard status
   */
  async getWizardStatus() {
    try {
      const response = await apiClient.get('wizard/status')
      return response.data
    } catch (error) {
      // Fallback if endpoint doesn't exist yet
      return { completed: false }
    }
  },
}

export default wizardApi
