import { apiClient } from './client'

/**
 * Outbox API methods
 * Handles sent SMS message history operations
 */
export const outboxApi = {
  /**
   * Get outbox messages with pagination and filters
   * @param {object} params - Query parameters
   * @param {number} params.page - Page number
   * @param {number} params.per_page - Items per page
   * @param {string} params.search - Search query
   * @param {string} params.status - Filter by status (success/failed/all)
   * @param {string} params.date_from - Start date (YYYY-MM-DD)
   * @param {string} params.date_to - End date (YYYY-MM-DD)
   * @param {string} params.orderby - Order by column
   * @param {string} params.order - Order direction (asc/desc)
   * @returns {Promise<object>} Outbox data
   */
  async getMessages(params = {}) {
    const response = await apiClient.get('outbox', params)
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
        success: 0,
        failed: 0,
      },
    }
  },

  /**
   * Get single message details
   * @param {number} id - Message ID
   * @returns {Promise<object>} Message details
   */
  async getMessage(id) {
    const response = await apiClient.get(`outbox/${id}`)
    return response.data
  },

  /**
   * Delete a message
   * @param {number} id - Message ID
   * @returns {Promise<object>} Delete result
   */
  async deleteMessage(id) {
    const response = await apiClient.delete(`outbox/${id}`)
    return {
      success: true,
      message: response.message,
    }
  },

  /**
   * Resend a message
   * @param {number} id - Message ID
   * @returns {Promise<object>} Resend result
   */
  async resendMessage(id) {
    const response = await apiClient.post(`outbox/${id}/resend`, {})
    return {
      success: true,
      message: response.message,
      credit: response.data?.credit,
    }
  },

  /**
   * Bulk action on messages
   * @param {string} action - Action type (delete/resend)
   * @param {number[]} ids - Message IDs
   * @returns {Promise<object>} Bulk action result
   */
  async bulkAction(action, ids) {
    const response = await apiClient.post('outbox/bulk', { action, ids })
    return {
      success: true,
      message: response.message,
      affected: response.data?.affected || 0,
      errors: response.data?.errors || [],
    }
  },

  /**
   * Export outbox messages to CSV
   * @param {object} params - Export parameters
   * @param {string} params.status - Filter by status (success/failed/all)
   * @param {string} params.date_from - Start date (YYYY-MM-DD)
   * @param {string} params.date_to - End date (YYYY-MM-DD)
   * @returns {Promise<object>} Export data
   */
  async exportCsv(params = {}) {
    const response = await apiClient.get('outbox/export', params)
    return {
      data: response.data?.data || [],
      filename: response.data?.filename || `outbox-export-${new Date().toISOString().split('T')[0]}.csv`,
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

export default outboxApi
