import { apiClient } from './client'

/**
 * Scheduled SMS API methods
 * Handles scheduled SMS message operations
 */
export const scheduledApi = {
  /**
   * Get scheduled messages with pagination and filters
   * @param {object} params - Query parameters
   * @param {number} params.page - Page number
   * @param {number} params.per_page - Items per page
   * @param {string} params.search - Search query
   * @param {string} params.status - Filter by status (pending/sent/failed/all)
   * @param {string} params.date_from - Start date (YYYY-MM-DD)
   * @param {string} params.date_to - End date (YYYY-MM-DD)
   * @param {string} params.orderby - Order by column
   * @param {string} params.order - Order direction (asc/desc)
   * @returns {Promise<object>} Scheduled messages data
   */
  async getMessages(params = {}) {
    const response = await apiClient.get('scheduled', params)
    return {
      items: response.data?.items || [],
      pagination: response.data?.pagination || {
        total: 0,
        total_pages: 1,
        current_page: 1,
        per_page: 20,
      },
      stats: response.data?.stats || {
        total: 0,
        pending: 0,
        sent: 0,
        failed: 0,
      },
    }
  },

  /**
   * Get single scheduled message details
   * @param {number} id - Message ID
   * @returns {Promise<object>} Message details
   */
  async getMessage(id) {
    const response = await apiClient.get(`scheduled/${id}`)
    return response.data
  },

  /**
   * Create a new scheduled message
   * @param {object} data - Message data
   * @param {string} data.message - SMS message content
   * @param {Array} data.recipients - Recipient phone numbers or groups
   * @param {string} data.scheduled_date - Scheduled date and time (ISO format)
   * @param {string} data.sender - Sender ID (optional)
   * @param {Array} data.media_urls - Media URLs for MMS (optional)
   * @returns {Promise<object>} Created message
   */
  async createMessage(data) {
    const response = await apiClient.post('scheduled', data)
    return {
      success: true,
      message: response.message,
      data: response.data,
    }
  },

  /**
   * Update a scheduled message
   * @param {number} id - Message ID
   * @param {object} data - Updated message data
   * @returns {Promise<object>} Updated message
   */
  async updateMessage(id, data) {
    const response = await apiClient.put(`scheduled/${id}`, data)
    return {
      success: true,
      message: response.message,
      data: response.data,
    }
  },

  /**
   * Delete a scheduled message
   * @param {number} id - Message ID
   * @returns {Promise<object>} Delete result
   */
  async deleteMessage(id) {
    const response = await apiClient.delete(`scheduled/${id}`)
    return {
      success: true,
      message: response.message,
    }
  },

  /**
   * Send a scheduled message immediately
   * @param {number} id - Message ID
   * @returns {Promise<object>} Send result
   */
  async sendNow(id) {
    const response = await apiClient.post(`scheduled/${id}/send`, {})
    return {
      success: true,
      message: response.message,
      credit: response.data?.credit,
    }
  },

  /**
   * Bulk action on scheduled messages
   * @param {string} action - Action type (delete/send)
   * @param {number[]} ids - Message IDs
   * @returns {Promise<object>} Bulk action result
   */
  async bulkAction(action, ids) {
    const response = await apiClient.post('scheduled/bulk', { action, ids })
    return {
      success: true,
      message: response.message,
      affected: response.data?.affected || 0,
      errors: response.data?.errors || [],
    }
  },

  /**
   * Export scheduled messages to CSV
   * @param {object} params - Export parameters
   * @param {string} params.status - Filter by status
   * @param {string} params.date_from - Start date (YYYY-MM-DD)
   * @param {string} params.date_to - End date (YYYY-MM-DD)
   * @returns {Promise<object>} Export data
   */
  async exportCsv(params = {}) {
    const response = await apiClient.get('scheduled/export', params)
    return {
      data: response.data?.data || [],
      filename: response.data?.filename || `scheduled-export-${new Date().toISOString().split('T')[0]}.csv`,
      count: response.data?.count || 0,
    }
  },

  /**
   * Download CSV data as file
   * @param {Array} data - CSV rows
   * @param {string} filename - Output filename
   */
  downloadCsv(data, filename) {
    const csvContent = data.map((row) => row.join(',')).join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  },
}

export default scheduledApi
