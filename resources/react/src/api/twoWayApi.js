import { getWpSettings } from '../lib/utils'

/**
 * Two-Way SMS API client
 * Uses the wp-sms-two-way/v1 namespace
 */
class TwoWayApiClient {
  constructor() {
    const { nonce } = getWpSettings()
    this.baseUrl = '/wp-json/wp-sms-two-way/v1/'
    this.nonce = nonce
  }

  /**
   * Build query string from params
   */
  buildQueryString(params) {
    if (!params || Object.keys(params).length === 0) return ''
    const searchParams = new URLSearchParams()
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        searchParams.append(key, value)
      }
    })
    const queryString = searchParams.toString()
    return queryString ? `?${queryString}` : ''
  }

  /**
   * Make an API request
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
      const data = await response.json()

      if (!response.ok) {
        throw new Error(data?.message || `HTTP error! status: ${response.status}`)
      }

      return data
    } catch (error) {
      console.error('Two-Way API Error:', error)
      throw error
    }
  }

  async get(endpoint, params = {}) {
    const queryString = this.buildQueryString(params)
    return this.request(`${endpoint}${queryString}`, { method: 'GET' })
  }

  async post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  }
}

const twoWayClient = new TwoWayApiClient()

/**
 * Inbox API
 */
export const inboxApi = {
  /**
   * Get inbox messages with pagination
   */
  async getMessages(params = {}) {
    return twoWayClient.get('inbox', params)
  },

  /**
   * Get inbox statistics
   */
  async getStats() {
    return twoWayClient.get('inbox/stats')
  },

  /**
   * Get single message
   */
  async getMessage(id) {
    return twoWayClient.get(`inbox/${id}`)
  },

  /**
   * Delete message
   */
  async deleteMessage(id) {
    return twoWayClient.post(`inbox/${id}/delete`)
  },

  /**
   * Reply to message
   */
  async replyToMessage(id, message) {
    return twoWayClient.post(`inbox/${id}/reply`, { message })
  },

  /**
   * Mark message as read
   */
  async markAsRead(id) {
    return twoWayClient.post(`inbox/${id}/read`)
  },

  /**
   * Bulk delete messages
   */
  async bulkDelete(ids) {
    return twoWayClient.post('inbox/bulk-delete', { ids })
  },

  /**
   * Get commands for filter dropdown
   */
  async getCommands() {
    return twoWayClient.get('inbox/commands')
  },

  /**
   * Export messages as CSV
   */
  async exportMessages(params = {}) {
    return twoWayClient.get('inbox/export', params)
  },
}

/**
 * Commands API
 */
export const commandsApi = {
  /**
   * Get commands list
   */
  async getCommands(params = {}) {
    return twoWayClient.get('commands', params)
  },

  /**
   * Get available actions
   */
  async getActions() {
    return twoWayClient.get('commands/actions')
  },

  /**
   * Get single command
   */
  async getCommand(id) {
    return twoWayClient.get(`commands/${id}`)
  },

  /**
   * Create command
   */
  async createCommand(data) {
    return twoWayClient.post('commands', data)
  },

  /**
   * Update command
   */
  async updateCommand(id, data) {
    return twoWayClient.post(`commands/${id}`, data)
  },

  /**
   * Delete command
   */
  async deleteCommand(id) {
    return twoWayClient.post(`commands/${id}/delete`)
  },

  /**
   * Toggle command status
   */
  async toggleCommand(id) {
    return twoWayClient.post(`commands/${id}/toggle`)
  },
}

export default { inboxApi, commandsApi }
