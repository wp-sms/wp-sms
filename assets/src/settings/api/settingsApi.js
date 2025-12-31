import { getWpSettings } from '../lib/utils'

/**
 * Default request timeout in milliseconds
 */
const DEFAULT_TIMEOUT = 10000

/**
 * Maximum retry attempts for network errors
 */
const MAX_RETRIES = 3

/**
 * Delay between retries in milliseconds
 */
const RETRY_DELAY = 1000

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
   * Sleep for a given duration
   * @param {number} ms - Milliseconds to sleep
   * @returns {Promise<void>}
   */
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms))
  }

  /**
   * Check if error is a network error that should be retried
   * @param {Error} error - The error to check
   * @returns {boolean}
   */
  isNetworkError(error) {
    return (
      error.name === 'AbortError' ||
      error.name === 'TypeError' ||
      error.message.includes('network') ||
      error.message.includes('fetch')
    )
  }

  /**
   * Safely parse JSON response
   * @param {Response} response - Fetch response
   * @returns {Promise<object|null>}
   */
  async safeParseJson(response) {
    try {
      const text = await response.text()
      return text ? JSON.parse(text) : null
    } catch (parseError) {
      console.warn('Failed to parse JSON response:', parseError)
      return null
    }
  }

  /**
   * Make an API request with timeout and retry logic
   * @param {string} endpoint - API endpoint
   * @param {object} options - Fetch options
   * @param {number} retryCount - Current retry attempt
   * @returns {Promise<object>} Response data
   */
  async request(endpoint, options = {}, retryCount = 0) {
    const url = `${this.baseUrl}${endpoint}`

    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': this.nonce,
      ...options.headers,
    }

    // Create abort controller for timeout
    const abortController = new AbortController()
    const timeoutId = setTimeout(() => abortController.abort(), DEFAULT_TIMEOUT)

    const config = {
      ...options,
      headers,
      signal: abortController.signal,
    }

    try {
      const response = await fetch(url, config)
      clearTimeout(timeoutId)

      if (!response.ok) {
        const errorData = await this.safeParseJson(response)
        const errorMessage = errorData?.error?.message ||
                            errorData?.message ||
                            `HTTP error! status: ${response.status}`
        throw new Error(errorMessage)
      }

      const data = await this.safeParseJson(response)

      if (!data) {
        throw new Error('Empty response from server')
      }

      // Check for API-level errors
      if (data.error && data.error.message) {
        throw new Error(data.error.message)
      }

      return data
    } catch (error) {
      clearTimeout(timeoutId)

      // Retry on network errors
      if (this.isNetworkError(error) && retryCount < MAX_RETRIES) {
        console.warn(`Request failed, retrying (${retryCount + 1}/${MAX_RETRIES})...`)
        await this.sleep(RETRY_DELAY * (retryCount + 1))
        return this.request(endpoint, options, retryCount + 1)
      }

      // Format user-friendly error message
      let userMessage = error.message
      if (error.name === 'AbortError') {
        userMessage = 'Request timed out. Please check your connection and try again.'
      } else if (this.isNetworkError(error)) {
        userMessage = 'Network error. Please check your internet connection.'
      }

      console.error('API Error:', error)
      throw new Error(userMessage)
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
