import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Merge class names with Tailwind CSS classes
 * @param {...string} inputs - Class names to merge
 * @returns {string} Merged class names
 */
export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

/**
 * Format a number as currency
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency code
 * @returns {string} Formatted currency string
 */
export function formatCurrency(amount, currency = 'USD') {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
  }).format(amount)
}

/**
 * Format a date string
 * @param {string|Date} date - Date to format
 * @param {object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
export function formatDate(date, options = {}) {
  const defaultOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }
  return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(new Date(date))
}

/**
 * Debounce a function
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 */
export function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

/**
 * Get the WordPress localized settings
 * @returns {object} Settings object
 */
export function getWpSettings() {
  return window.wpSmsSettings || {
    apiUrl: '/wp-json/wpsms/v1/',
    nonce: '',
    settings: {},
    proSettings: {},
    addons: {},
    addonSettings: {},
    gateways: {},
    countries: {},
    groups: {},
    postTypes: {},
    roles: {},
    taxonomies: {},
    gdprEnabled: false,
    i18n: {},
  }
}

/**
 * Get translation string
 * @param {string} key - Translation key
 * @param {string} fallback - Fallback string
 * @returns {string} Translated string
 */
export function __(key, fallback = key) {
  const { i18n } = getWpSettings()
  return i18n[key] || fallback
}

/**
 * Check if an add-on is active
 * @param {string} addon - Add-on key
 * @returns {boolean} Whether the add-on is active
 */
export function isAddonActive(addon) {
  const { addons } = getWpSettings()
  return addons[addon] === true
}

/**
 * Deep merge objects
 * @param {object} target - Target object
 * @param {object} source - Source object
 * @returns {object} Merged object
 */
export function deepMerge(target, source) {
  const output = { ...target }
  if (isObject(target) && isObject(source)) {
    Object.keys(source).forEach((key) => {
      if (isObject(source[key])) {
        if (!(key in target)) {
          Object.assign(output, { [key]: source[key] })
        } else {
          output[key] = deepMerge(target[key], source[key])
        }
      } else {
        Object.assign(output, { [key]: source[key] })
      }
    })
  }
  return output
}

/**
 * Check if value is an object
 * @param {*} item - Item to check
 * @returns {boolean} Whether the item is an object
 */
function isObject(item) {
  return item && typeof item === 'object' && !Array.isArray(item)
}
