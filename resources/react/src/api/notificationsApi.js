import apiClient from './client'

/**
 * Timeout for notifications API requests
 */
const NOTIFICATIONS_TIMEOUT = 10000

/**
 * Notifications API methods
 */
export const notificationsApi = {
  /**
   * Get all notifications
   * @returns {Promise<object>} Notifications data
   */
  async getNotifications() {
    const response = await apiClient.get('notifications', {}, { timeout: NOTIFICATIONS_TIMEOUT })
    return response.data
  },

  /**
   * Dismiss a single notification
   * @param {number} id - Notification ID
   * @returns {Promise<object>} Response
   */
  async dismissNotification(id) {
    const response = await apiClient.post(
      'notifications/dismiss',
      { id: String(id) },
      { timeout: NOTIFICATIONS_TIMEOUT }
    )
    return response.data
  },

  /**
   * Dismiss all notifications
   * @returns {Promise<object>} Response
   */
  async dismissAllNotifications() {
    const response = await apiClient.post(
      'notifications/dismiss',
      { id: 'all' },
      { timeout: NOTIFICATIONS_TIMEOUT }
    )
    return response.data
  },
}

export default notificationsApi
