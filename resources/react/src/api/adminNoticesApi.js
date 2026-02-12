import apiClient from './client'

const TIMEOUT = 10000

export const adminNoticesApi = {
  async dismiss(id, store) {
    const response = await apiClient.post(
      'admin-notices/dismiss',
      { id, store },
      { timeout: TIMEOUT }
    )
    return response.data
  },

  async executeAction(id, action) {
    const response = await apiClient.post(
      'admin-notices/action',
      {
        id,
        action_type: action.action_type,
        option: action.option,
        value: action.value,
      },
      { timeout: TIMEOUT }
    )
    return response.data
  },
}

export default adminNoticesApi
