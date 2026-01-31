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
 * @returns {object} Settings object with features flattened to top level
 */
export function getWpSettings() {
  const wpSettings = window.wpSmsSettings || {
    apiUrl: '/wp-json/wpsms/v1/',
    nonce: '',
    settings: {},
    proSettings: {},
    addons: {},
    addonSettings: {},
    gateways: [],
    countries: {},
    groups: {},
    postTypes: {},
    roles: {},
    taxonomies: {},
    features: {},
    i18n: {},
  }

  // Flatten features to top level for easy access
  const features = wpSettings.features || {}
  return {
    ...wpSettings,
    gdprEnabled: features.gdprEnabled || false,
    hasProAddon: features.hasProAddon || features.isProActive || false,
    twoWayEnabled: features.twoWayEnabled || false,
    scheduledSms: features.scheduledSms || false,
    isProActive: features.isProActive || false,
    isWooActive: features.isWooActive || false,
    isBuddyPressActive: features.isBuddyPressActive || false,
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

/**
 * Get the display name for a gateway key
 * Supports both legacy nested-by-region object and new flat array format
 * @param {string} gatewayKey - The gateway key/slug
 * @param {object|Array} gateways - Gateways data (legacy object or new array)
 * @returns {string} Display name or the key if not found
 */
export function getGatewayDisplayName(gatewayKey, gateways) {
  if (!gatewayKey || !gateways) return gatewayKey || ''

  // New format: array of gateway objects with slug/name
  if (Array.isArray(gateways)) {
    const found = gateways.find((g) => g.slug === gatewayKey)
    if (found) return found.name
    return gatewayKey
  }

  // Legacy format: nested by region { global: { twilio: "twilio.com" } }
  for (const region of Object.values(gateways)) {
    if (typeof region === 'object' && region[gatewayKey]) {
      return region[gatewayKey]
    }
  }

  return gatewayKey
}

/**
 * Escape a value for CSV format
 * @param {*} value - Value to escape
 * @returns {string} Escaped CSV value
 */
export function escapeCsvValue(value) {
  if (value === null || value === undefined) {
    return ''
  }
  const str = String(value)
  // If value contains comma, quote, or newline, wrap in quotes and escape internal quotes
  if (str.includes(',') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
    return '"' + str.replace(/"/g, '""') + '"'
  }
  return str
}

/**
 * Convert array data to CSV content string
 * @param {Array} data - Array of rows, each row is an array of cells
 * @returns {string} CSV content string
 */
export function arrayToCsv(data) {
  return data.map((row) => row.map((cell) => escapeCsvValue(cell)).join(',')).join('\n')
}

/**
 * Download data as CSV file
 * @param {Array} data - Array of rows, each row is an array of cells
 * @param {string} filename - Output filename
 */
/**
 * Get the best logo URL from a gateway object
 * Handles both string and object ({ square, rectangular }) formats
 * @param {object} gateway - Gateway object
 * @returns {string} Logo URL or empty string
 */
export function getGatewayLogo(gateway) {
  if (!gateway?.logo) return ''
  if (typeof gateway.logo === 'string') return gateway.logo
  return gateway.logo.square || gateway.logo.rectangular || ''
}

/**
 * Convert a 2-letter country code to a flag emoji
 * @param {string} code - ISO 3166-1 alpha-2 country code
 * @returns {string} Flag emoji or empty string
 */
export function countryCodeToFlag(code) {
  if (!code || code.length !== 2) return ''
  return String.fromCodePoint(...[...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65))
}

export function downloadCsv(data, filename) {
  const csvContent = arrayToCsv(data)
  // Add BOM for Excel UTF-8 compatibility
  const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

