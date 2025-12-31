import { getWpSettings } from '../lib/utils'

/**
 * Base API client for settings
 */
class SettingsApiClient {
  constructor() {
    const { apiUrl, nonce } = getWpSettings()
    this.baseUrl = apiUrl
    this.nonce = nonce
  }

  /**
   * Make an API request
   * @param {string} endpoint - API endpoint
   * @param {object} options - Fetch options
   * @returns {Promise<object>} Response data
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`

    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': this.nonce,
      ...options.headers,
    }

    const config = {
      ...options,
      headers,
    }

    try {
      const response = await fetch(url, config)

      if (!response.ok) {
        const error = await response.json()
        throw new Error(error.error?.message || `HTTP error! status: ${response.status}`)
      }

      const data = await response.json()

      // Check for API-level errors
      if (data.error && data.error.message) {
        throw new Error(data.error.message)
      }

      return data
    } catch (error) {
      console.error('API Error:', error)
      throw error
    }
  }

  /**
   * GET request
   * @param {string} endpoint - API endpoint
   * @returns {Promise<object>} Response data
   */
  async get(endpoint) {
    return this.request(endpoint, { method: 'GET' })
  }

  /**
   * POST request
   * @param {string} endpoint - API endpoint
   * @param {object} data - Request body
   * @returns {Promise<object>} Response data
   */
  async post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  }

  /**
   * PUT request
   * @param {string} endpoint - API endpoint
   * @param {object} data - Request body
   * @returns {Promise<object>} Response data
   */
  async put(endpoint, data) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    })
  }

  /**
   * DELETE request
   * @param {string} endpoint - API endpoint
   * @returns {Promise<object>} Response data
   */
  async delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' })
  }
}

// Create API instance
const apiClient = new SettingsApiClient()

/**
 * Settings API methods
 */
export const settingsApi = {
  /**
   * Get all settings
   * @returns {Promise<object>} Settings data
   */
  async getSettings() {
    const response = await apiClient.get('settings')
    return response.data
  },

  /**
   * Get settings for a specific section
   * @param {string} section - Section name
   * @returns {Promise<object>} Section settings
   */
  async getSection(section) {
    const response = await apiClient.get(`settings/${section}`)
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
    const response = await apiClient.post('settings', data)
    return response.data
  },

  /**
   * Test gateway connection
   * @returns {Promise<object>} Gateway test result
   */
  async testGateway() {
    const response = await apiClient.post('settings/test-gateway', {})
    return {
      success: true,
      credit: response.data?.credit,
      gateway: response.data?.gateway,
      message: response.message,
    }
  },
}

export default settingsApi
