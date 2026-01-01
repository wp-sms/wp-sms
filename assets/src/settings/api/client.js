import { getWpSettings } from '../lib/utils'

/**
 * Default request timeout in milliseconds
 */
const DEFAULT_TIMEOUT = 30000

/**
 * Maximum retry attempts for network errors
 */
const MAX_RETRIES = 3

/**
 * Delay between retries in milliseconds
 */
const RETRY_DELAY = 1000

/**
 * Base API client for WSMS REST API
 */
class ApiClient {
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
    return new Promise((resolve) => setTimeout(resolve, ms))
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
   * Build query string from params object
   * @param {object} params - Query parameters
   * @returns {string} Query string
   */
  buildQueryString(params) {
    if (!params || Object.keys(params).length === 0) return ''

    const searchParams = new URLSearchParams()
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (Array.isArray(value)) {
          value.forEach((v) => searchParams.append(`${key}[]`, v))
        } else {
          searchParams.append(key, value)
        }
      }
    })

    const queryString = searchParams.toString()
    return queryString ? `?${queryString}` : ''
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

    // Remove Content-Type for FormData
    if (options.body instanceof FormData) {
      delete headers['Content-Type']
    }

    // Create abort controller for timeout
    const abortController = new AbortController()
    const timeout = options.timeout || DEFAULT_TIMEOUT
    const timeoutId = setTimeout(() => abortController.abort(), timeout)

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
        const errorMessage =
          errorData?.error?.message ||
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
   * @param {object} params - Query parameters
   * @param {object} options - Additional options (e.g., timeout)
   * @returns {Promise<object>} Response data
   */
  async get(endpoint, params = {}, options = {}) {
    const queryString = this.buildQueryString(params)
    return this.request(`${endpoint}${queryString}`, { method: 'GET', ...options })
  }

  /**
   * POST request
   * @param {string} endpoint - API endpoint
   * @param {object} data - Request body
   * @param {object} options - Additional options (e.g., timeout)
   * @returns {Promise<object>} Response data
   */
  async post(endpoint, data, options = {}) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
      ...options,
    })
  }

  /**
   * PUT request
   * @param {string} endpoint - API endpoint
   * @param {object} data - Request body
   * @param {object} options - Additional options (e.g., timeout)
   * @returns {Promise<object>} Response data
   */
  async put(endpoint, data, options = {}) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
      ...options,
    })
  }

  /**
   * DELETE request
   * @param {string} endpoint - API endpoint
   * @param {object} options - Additional options (e.g., timeout)
   * @returns {Promise<object>} Response data
   */
  async delete(endpoint, options = {}) {
    return this.request(endpoint, { method: 'DELETE', ...options })
  }

  /**
   * Upload file via POST
   * @param {string} endpoint - API endpoint
   * @param {FormData} formData - Form data with file
   * @returns {Promise<object>} Response data
   */
  async upload(endpoint, formData) {
    return this.request(endpoint, {
      method: 'POST',
      body: formData,
      timeout: 60000, // Longer timeout for uploads
    })
  }
}

// Export singleton instance
export const apiClient = new ApiClient()

// Export class for testing and extension
export { ApiClient }

// Export constants for use by other modules
export { DEFAULT_TIMEOUT, MAX_RETRIES, RETRY_DELAY }

export default apiClient
