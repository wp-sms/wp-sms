import { ApiClient } from './client'
import { __ } from '@/lib/utils'

/**
 * WooCommerce Pro API client
 * Uses a separate base URL from the main plugin API
 */
class WooCommerceProClient extends ApiClient {
  constructor() {
    super()
    this.baseUrl = '/wp-json/wp-sms-woo-pro/v1/'
  }
}

const wooProClient = new WooCommerceProClient()

/**
 * WooCommerce Pro API methods
 */
export const woocommerceProApi = {
  // ── Campaigns ──────────────────────────────────────────

  /**
   * Get campaigns list with pagination
   * @param {object} params - Query parameters (page, per_page, status, search)
   * @returns {Promise<{items, pagination}>}
   */
  async getCampaigns(params = {}) {
    const response = await wooProClient.get('campaigns', params)
    const data = response.data || response
    return {
      items: data.campaigns || [],
      pagination: {
        total: data.total || 0,
        total_pages: data.total_pages || 1,
        current_page: parseInt(params.page) || 1,
        per_page: parseInt(params.per_page) || 10,
      },
    }
  },

  /**
   * Get single campaign
   * @param {number} id - Campaign ID
   * @returns {Promise<object>}
   */
  async getCampaign(id) {
    const response = await wooProClient.get(`campaigns/${id}`)
    return response.data || response
  },

  /**
   * Create a new campaign
   * @param {object} data - Campaign data
   * @returns {Promise<object>}
   */
  async createCampaign(data) {
    return wooProClient.post('campaigns', data)
  },

  /**
   * Update an existing campaign
   * @param {number} id - Campaign ID
   * @param {object} data - Campaign data
   * @returns {Promise<object>}
   */
  async updateCampaign(id, data) {
    return wooProClient.post(`campaigns/${id}`, data)
  },

  /**
   * Delete a campaign
   * @param {number} id - Campaign ID
   * @returns {Promise<object>}
   */
  async deleteCampaign(id, { force = false } = {}) {
    return wooProClient.post(`campaigns/${id}/delete`, { force })
  },

  /**
   * Get condition options for campaign form
   * @returns {Promise<object>}
   */
  async getConditionOptions() {
    return wooProClient.get('campaigns/conditions')
  },

  // ── Cart Abandonment ───────────────────────────────────

  /**
   * Get abandoned carts with filters
   * @param {object} params - Query parameters (duration, type, search)
   * @returns {Promise<{items, stats}>}
   */
  async getCarts(params = {}) {
    const response = await wooProClient.get('cart-abandonment', params)
    const data = response.data || response
    return {
      items: data.carts || [],
      stats: data.stats || {},
      pagination: {
        total: (data.carts || []).length,
        total_pages: 1,
        current_page: 1,
        per_page: 100,
      },
    }
  },

  /**
   * Delete an abandoned cart
   * @param {string} customerId - Customer ID
   * @param {string} cartHash - Cart hash
   * @returns {Promise<object>}
   */
  async deleteCart(customerId, cartHash) {
    return wooProClient.post('cart-abandonment/delete', {
      customer_id: customerId,
      cart_hash: cartHash,
    })
  },
}

export default woocommerceProApi
