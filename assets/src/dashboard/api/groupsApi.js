import { apiClient } from './client'

/**
 * Groups API methods
 * Handles subscriber group CRUD operations
 */
export const groupsApi = {
  /**
   * Get all groups
   * @param {object} params - Query parameters
   * @param {number} params.page - Page number
   * @param {number} params.per_page - Items per page
   * @param {string} params.search - Search query
   * @returns {Promise<object>} Groups data
   */
  async getGroups(params = {}) {
    const response = await apiClient.get('groups', params)
    return {
      items: response.data?.items || [],
      pagination: response.data?.pagination || {
        total: 0,
        total_pages: 1,
        current_page: 1,
        per_page: 20,
      },
    }
  },

  /**
   * Get single group details
   * @param {number} id - Group ID
   * @returns {Promise<object>} Group details
   */
  async getGroup(id) {
    const response = await apiClient.get(`groups/${id}`)
    return response.data
  },

  /**
   * Create a new group
   * @param {object} data - Group data
   * @param {string} data.name - Group name
   * @returns {Promise<object>} Created group
   */
  async createGroup(data) {
    const response = await apiClient.post('groups', data)
    return {
      success: true,
      message: response.message,
      group: response.data,
    }
  },

  /**
   * Update a group
   * @param {number} id - Group ID
   * @param {object} data - Group data
   * @param {string} data.name - Group name
   * @returns {Promise<object>} Updated group
   */
  async updateGroup(id, data) {
    const response = await apiClient.put(`groups/${id}`, data)
    return {
      success: true,
      message: response.message,
      group: response.data,
    }
  },

  /**
   * Delete a group
   * @param {number} id - Group ID
   * @returns {Promise<object>} Delete result
   */
  async deleteGroup(id) {
    const response = await apiClient.delete(`groups/${id}`)
    return {
      success: true,
      message: response.message,
    }
  },

  /**
   * Get all groups as simple list (for selects)
   * @returns {Promise<object[]>} Groups list
   */
  async getGroupsList() {
    const response = await apiClient.get('groups', { per_page: 100 })
    return (response.data?.items || []).map((group) => ({
      id: group.id,
      name: group.name,
      count: group.subscriber_count,
    }))
  },
}

export default groupsApi
