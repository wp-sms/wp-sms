import { apiClient } from './client'

/**
 * Privacy API methods
 * Handles GDPR compliance operations (search, export, delete user data)
 */
export const privacyApi = {
  /**
   * Search for data associated with a phone number
   * @param {string} mobile - Phone number to search
   * @returns {Promise<object>} Search results
   */
  async searchData(mobile) {
    const response = await apiClient.post('privacy/search', { mobile })
    return {
      found: response.data?.found || false,
      records: response.data?.records || [],
      summary: response.data?.summary || {
        total_records: 0,
        wp_users: 0,
        subscribers: 0,
        outbox_messages: 0,
      },
    }
  },

  /**
   * Export all data for a phone number
   * @param {string} mobile - Phone number
   * @returns {Promise<object>} Export data
   */
  async exportData(mobile) {
    const response = await apiClient.post('privacy/export', { mobile })
    return {
      csvData: response.data?.csv_data || [],
      filename: response.data?.filename || 'privacy-export.csv',
      count: response.data?.count || 0,
    }
  },

  /**
   * Delete all data for a phone number
   * @param {string} mobile - Phone number
   * @param {boolean} confirm - Confirmation flag (must be true)
   * @returns {Promise<object>} Delete result
   */
  async deleteData(mobile, confirm = false) {
    if (!confirm) {
      throw new Error('Deletion must be confirmed')
    }

    const response = await apiClient.post('privacy/delete', { mobile, confirm: true })
    return {
      success: true,
      message: response.message,
      deleted: response.data?.deleted || {},
      total: response.data?.total || 0,
    }
  },

  /**
   * Download CSV data as file
   * @param {string[][]} csvData - CSV data array
   * @param {string} filename - File name
   */
  downloadCsv(csvData, filename) {
    const csvContent = csvData.map((row) => row.map((cell) => `"${cell}"`).join(',')).join('\n')
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

export default privacyApi
