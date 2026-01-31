import apiClient from './client'

/**
 * Timeout for settings API requests (shorter than default)
 */
const SETTINGS_TIMEOUT = 10000

/**
 * Settings API methods
 */
export const settingsApi = {
  /**
   * Get all settings
   * @returns {Promise<object>} Settings data
   */
  async getSettings() {
    const response = await apiClient.get('settings', {}, { timeout: SETTINGS_TIMEOUT })
    return response.data
  },

  /**
   * Get settings for a specific section
   * @param {string} section - Section name
   * @returns {Promise<object>} Section settings
   */
  async getSection(section) {
    const response = await apiClient.get(`settings/${section}`, {}, { timeout: SETTINGS_TIMEOUT })
    return response.data
  },

  /**
   * Update settings
   * @param {object} data - Settings data
   * @param {object} data.settings - Main settings
   * @param {object} data.proSettings - Pro settings
   * @returns {Promise<object>} Updated settings
   */
  async updateSettings(data) {
    const response = await apiClient.post('settings', data, { timeout: SETTINGS_TIMEOUT })
    return response.data
  },

  /**
   * Get gateway registry (API-sourced with local fallback)
   * @returns {Promise<object>} { source, gateways, regions }
   */
  async getGatewayRegistry() {
    const response = await apiClient.get('settings/gateways', {}, { timeout: SETTINGS_TIMEOUT })
    return response.data
  },

  /**
   * Test gateway connection
   * @returns {Promise<object>} Gateway test result
   */
  async testGateway() {
    const response = await apiClient.post('settings/test-gateway', {}, { timeout: SETTINGS_TIMEOUT })
    return {
      success: true,
      credit: response.data?.credit,
      gateway: response.data?.gateway,
      message: response.message,
    }
  },
}

export default settingsApi
