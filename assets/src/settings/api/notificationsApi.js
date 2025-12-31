import { getWpSettings } from '../lib/utils'

/**
 * Default request timeout in milliseconds
 */
const DEFAULT_TIMEOUT = 10000

/**
 * Notifications API client
 */
class NotificationsApiClient {
  constructor() {
    const { apiUrl, nonce } = getWpSettings()
    this.baseUrl = apiUrl
    this.nonce = nonce
  }

  /**
   * Make an API request with timeout
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
        const errorData = await response.json().catch(() => null)
        const errorMessage = errorData?.message || `HTTP error! status: ${response.status}`
        throw new Error(errorMessage)
      }

      const data = await response.json()

      if (data.error && data.error.message) {
        throw new Error(data.error.message)
      }

      return data
    } catch (error) {
      clearTimeout(timeoutId)

      if (error.name === 'AbortError') {
        throw new Error('Request timed out')
      }

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
}

// Create API instance
const apiClient = new NotificationsApiClient()

/**
 * Notifications API methods
 */
export const notificationsApi = {
  /**
   * Get all notifications
   * @returns {Promise<object>} Notifications data
   */
  async getNotifications() {
    const response = await apiClient.get('notifications')
    return response.data
  },

  /**
   * Dismiss a single notification
   * @param {number} id - Notification ID
   * @returns {Promise<object>} Response
   */
  async dismissNotification(id) {
    const response = await apiClient.post('notifications/dismiss', { id: String(id) })
    return response.data
  },

  /**
   * Dismiss all notifications
   * @returns {Promise<object>} Response
   */
  async dismissAllNotifications() {
    const response = await apiClient.post('notifications/dismiss', { id: 'all' })
    return response.data
  },
}

export default notificationsApi
