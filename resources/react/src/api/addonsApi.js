import apiClient from './client'

/**
 * Add-Ons API methods
 */
export const addonsApi = {
  /**
   * Get all add-ons
   * @returns {Promise<object>} Add-ons data
   */
  async getAll() {
    const response = await apiClient.get('addons')
    return response.data
  },

  /**
   * Activate a license for an add-on
   * @param {string} addonSlug - The add-on slug
   * @param {string} licenseKey - The license key
   * @returns {Promise<object>} Activation result
   */
  async activateLicense(addonSlug, licenseKey) {
    const response = await apiClient.post('addons/activate-license', {
      addon_slug: addonSlug || '',
      license_key: licenseKey,
    })
    return response
  },

  /**
   * Remove a license for an add-on
   * @param {string} addonSlug - The add-on slug
   * @returns {Promise<object>} Removal result
   */
  async removeLicense(addonSlug, force = false, maskedKey = '') {
    const response = await apiClient.post('addons/remove-license', {
      addon_slug: addonSlug,
      force,
      masked_key: maskedKey,
    })
    return response
  },
}

export default addonsApi
