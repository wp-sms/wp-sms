import { apiClient } from './client'

/**
 * Repeating Messages API methods
 * Handles recurring/repeating SMS message operations
 */
export const repeatingApi = {
  /**
   * Get repeating messages with pagination and filters
   * @param {object} params - Query parameters
   * @param {number} params.page - Page number
   * @param {number} params.per_page - Items per page
   * @param {string} params.search - Search query
   * @param {string} params.status - Filter by status (active/paused/completed/all)
   * @param {string} params.interval_unit - Filter by interval unit (minute/hour/day/week/month)
   * @param {string} params.orderby - Order by column
   * @param {string} params.order - Order direction (asc/desc)
   * @returns {Promise<object>} Repeating messages data
   */
  async getMessages(params = {}) {
    const response = await apiClient.get('repeating', params)
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
        active: 0,
        paused: 0,
        completed: 0,
      },
    }
  },

  /**
   * Get single repeating message details
   * @param {number} id - Message ID
   * @returns {Promise<object>} Message details
   */
  async getMessage(id) {
    const response = await apiClient.get(`repeating/${id}`)
    return response.data
  },

  /**
   * Create a new repeating message
   * @param {object} data - Message data
   * @param {string} data.message - SMS message content
   * @param {Array} data.recipients - Recipient phone numbers or groups
   * @param {number} data.interval_value - Interval value (e.g., 1, 2, 3)
   * @param {string} data.interval_unit - Interval unit (minute/hour/day/week/month)
   * @param {string} data.start_date - Start date and time (ISO format)
   * @param {string} data.end_date - End date and time (ISO format, optional)
   * @param {number} data.max_occurrences - Maximum number of occurrences (optional)
   * @param {string} data.sender - Sender ID (optional)
   * @param {Array} data.media_urls - Media URLs for MMS (optional)
   * @returns {Promise<object>} Created message
   */
  async createMessage(data) {
    const response = await apiClient.post('repeating', data)
    return {
      success: true,
      message: response.message,
      data: response.data,
    }
  },

  /**
   * Update a repeating message
   * @param {number} id - Message ID
   * @param {object} data - Updated message data
   * @returns {Promise<object>} Updated message
   */
  async updateMessage(id, data) {
    const response = await apiClient.put(`repeating/${id}`, data)
    return {
      success: true,
      message: response.message,
      data: response.data,
    }
  },

  /**
   * Delete a repeating message
   * @param {number} id - Message ID
   * @returns {Promise<object>} Delete result
   */
  async deleteMessage(id) {
    const response = await apiClient.delete(`repeating/${id}`)
    return {
      success: true,
      message: response.message,
    }
  },

  /**
   * Pause a repeating message
   * @param {number} id - Message ID
   * @returns {Promise<object>} Pause result
   */
  async pauseMessage(id) {
    const response = await apiClient.post(`repeating/${id}/pause`, {})
    return {
      success: true,
      message: response.message,
      data: response.data,
    }
  },

  /**
   * Resume a paused repeating message
   * @param {number} id - Message ID
   * @returns {Promise<object>} Resume result
   */
  async resumeMessage(id) {
    const response = await apiClient.post(`repeating/${id}/resume`, {})
    return {
      success: true,
      message: response.message,
      data: response.data,
    }
  },

  /**
   * Bulk action on repeating messages
   * @param {string} action - Action type (delete/pause/resume)
   * @param {number[]} ids - Message IDs
   * @returns {Promise<object>} Bulk action result
   */
  async bulkAction(action, ids) {
    const response = await apiClient.post('repeating/bulk', { action, ids })
    return {
      success: true,
      message: response.message,
      affected: response.data?.affected || 0,
      errors: response.data?.errors || [],
    }
  },

  /**
   * Get interval unit options for UI
   * @returns {Array<object>} Interval unit options
   */
  getIntervalUnitOptions() {
    return [
      { value: 'minute', label: 'Minute(s)' },
      { value: 'hour', label: 'Hour(s)' },
      { value: 'day', label: 'Day(s)' },
      { value: 'week', label: 'Week(s)' },
      { value: 'month', label: 'Month(s)' },
    ]
  },

  /**
   * Format interval for display
   * @param {number} value - Interval value
   * @param {string} unit - Interval unit
   * @returns {string} Formatted interval string
   */
  formatInterval(value, unit) {
    const unitLabels = {
      minute: value === 1 ? 'minute' : 'minutes',
      hour: value === 1 ? 'hour' : 'hours',
      day: value === 1 ? 'day' : 'days',
      week: value === 1 ? 'week' : 'weeks',
      month: value === 1 ? 'month' : 'months',
    }
    return `Every ${value} ${unitLabels[unit] || unit}`
  },

  /**
   * Export repeating messages to CSV
   * @param {object} params - Export parameters
   * @param {string} params.status - Filter by status
   * @returns {Promise<object>} Export data
   */
  async exportCsv(params = {}) {
    const response = await apiClient.get('repeating/export', params)
    return {
      data: response.data?.data || [],
      filename: response.data?.filename || `repeating-export-${new Date().toISOString().split('T')[0]}.csv`,
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

export default repeatingApi
