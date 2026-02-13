import { ApiClient } from './client'
import { buildRestUrl } from '@/lib/utils'

/**
 * Two-Way SMS API client
 * Uses the wp-sms-two-way/v1 namespace
 */
class TwoWayApiClient extends ApiClient {
  constructor() {
    super()
    // IMPORTANT: do not hardcode "/wp-json/..." because Plain permalinks switch REST routing
    // to `?rest_route=...` URLs. `buildRestUrl()` derives the correct base from localized settings.
    this.baseUrl = buildRestUrl('wp-sms-two-way/v1/')
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
    return twoWayClient.post(`inbox/${id}/delete`, {})
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
    return twoWayClient.post(`inbox/${id}/read`, {})
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
   * Get conversation thread for a sender number
   */
  async getConversation(senderNumber) {
    return twoWayClient.get(`inbox/conversation/${encodeURIComponent(senderNumber)}`)
  },

  /**
   * Reply to a conversation by sender number
   */
  async replyToConversation(senderNumber, message) {
    return twoWayClient.post(`inbox/conversation/${encodeURIComponent(senderNumber)}/reply`, { message })
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
    return twoWayClient.post(`commands/${id}/delete`, {})
  },

  /**
   * Toggle command status
   */
  async toggleCommand(id) {
    return twoWayClient.post(`commands/${id}/toggle`, {})
  },
}

export default { inboxApi, commandsApi }
