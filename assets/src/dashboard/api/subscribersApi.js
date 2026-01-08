import { apiClient } from './client'

/**
 * Subscribers API methods
 * Handles subscriber CRUD operations
 */
export const subscribersApi = {
  /**
   * Get subscribers with pagination and filters
   * @param {object} params - Query parameters
   * @param {number} params.page - Page number
   * @param {number} params.per_page - Items per page
   * @param {string} params.search - Search query
   * @param {number} params.group_id - Filter by group ID
   * @param {string} params.status - Filter by status (active/inactive/all)
   * @param {string} params.orderby - Order by column
   * @param {string} params.order - Order direction (asc/desc)
   * @returns {Promise<object>} Subscribers data
   */
  async getSubscribers(params = {}) {
    const response = await apiClient.get('subscribers', params)
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
        inactive: 0,
      },
    }
  },

  /**
   * Get single subscriber details
   * @param {number} id - Subscriber ID
   * @returns {Promise<object>} Subscriber details
   */
  async getSubscriber(id) {
    const response = await apiClient.get(`subscribers/${id}`)
    return response.data
  },

  /**
   * Create a new subscriber
   * @param {object} data - Subscriber data
   * @param {string} data.name - Subscriber name
   * @param {string} data.mobile - Phone number
   * @param {number} data.group_id - Group ID
   * @param {string} data.status - Status (1=active, 0=inactive)
   * @returns {Promise<object>} Created subscriber
   */
  async createSubscriber(data) {
    const response = await apiClient.post('subscribers', data)
    return {
      success: true,
      message: response.message,
      subscriber: response.data,
    }
  },

  /**
   * Update a subscriber
   * @param {number} id - Subscriber ID
   * @param {object} data - Subscriber data
   * @returns {Promise<object>} Updated subscriber
   */
  async updateSubscriber(id, data) {
    const response = await apiClient.put(`subscribers/${id}`, data)
    return {
      success: true,
      message: response.message,
      subscriber: response.data,
    }
  },

  /**
   * Delete a subscriber
   * @param {number} id - Subscriber ID
   * @returns {Promise<object>} Delete result
   */
  async deleteSubscriber(id) {
    const response = await apiClient.delete(`subscribers/${id}`)
    return {
      success: true,
      message: response.message,
    }
  },

  /**
   * Bulk action on subscribers
   * @param {string} action - Action type (delete/activate/deactivate/move)
   * @param {number[]} ids - Subscriber IDs
   * @param {object} params - Additional params (e.g., group_id for move)
   * @returns {Promise<object>} Bulk action result
   */
  async bulkAction(action, ids, params = {}) {
    const response = await apiClient.post('subscribers/bulk', { action, ids, ...params })
    return {
      success: true,
      message: response.message,
      affected: response.data?.affected || 0,
    }
  },

  /**
   * Import subscribers from CSV
   * @param {File} file - CSV file
   * @param {object} options - Import options
   * @param {number} options.group_id - Group ID to import into
   * @param {boolean} options.skip_duplicates - Skip duplicate numbers
   * @returns {Promise<object>} Import result
   */
  async importCsv(file, options = {}) {
    const formData = new FormData()
    formData.append('file', file)
    if (options.group_id) {
      formData.append('group_id', options.group_id)
    }
    formData.append('skip_duplicates', options.skip_duplicates ? '1' : '0')

    const response = await apiClient.upload('subscribers/import', formData)
    return {
      success: true,
      message: response.message,
      imported: response.data?.imported || 0,
      skipped: response.data?.skipped || 0,
      errors: response.data?.errors || [],
    }
  },

  /**
   * Export subscribers to CSV
   * @param {object} params - Export parameters
   * @param {number} params.group_id - Filter by group ID
   * @param {string} params.status - Filter by status
   * @returns {Promise<object>} Export data
   */
  async exportCsv(params = {}) {
    const response = await apiClient.get('subscribers/export', params)
    return {
      data: response.data?.csv_data || [],
      filename: response.data?.filename || 'subscribers.csv',
      count: response.data?.count || 0,
    }
  },
}

export default subscribersApi
