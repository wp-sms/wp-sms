import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { formatDateWP } from './dateFormatter'

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
 * Format a date string using WordPress date/time format settings
 * @param {string|Date} date - Date to format
 * @param {boolean|object} options - true for time, or options object
 * @param {boolean} options.includeTime - Whether to include time
 * @param {string} options.hour - Legacy option, if set implies includeTime
 * @returns {string} Formatted date string
 */
export function formatDate(date, options = {}) {
  // Handle backward compatibility: formatDate(date, true)
  const opts = options === true ? { includeTime: true } : (options || {})
  const includeTime = opts.includeTime === true || opts.hour !== undefined
  return formatDateWP(date, {
    includeTime,
    dateFormat: window.wpSmsSettings?.dateFormat,
    timeFormat: window.wpSmsSettings?.timeFormat,
    timezone: window.wpSmsSettings?.timezone,
  })
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
  const getDefaultApiUrl = () => {
    const root = window.wpApiSettings?.root
    if (typeof root === 'string' && root.length > 0) {
      // `wpApiSettings.root` is built from WP's `rest_url()` and follows permalink mode.
      // Examples:
      // - Pretty permalinks: "https://example.com/wp-json/"
      // - Plain permalinks:  "https://example.com/?rest_route=/"
      if (root.includes('rest_route=')) {
        // Ensure we append to rest_route without creating a second "?".
        return root.replace(/rest_route=\/?$/, 'rest_route=/') + 'wpsms/v1/'
      }
      return root.replace(/\/?$/, '/') + 'wpsms/v1/'
    }

    // Fallback (should be overridden by localized `window.wpSmsSettings` in the dashboard).
    return '/wp-json/wpsms/v1/'
  }

  const wpSettings = window.wpSmsSettings || {
    apiUrl: getDefaultApiUrl(),
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
 * Build a WordPress REST API URL for any route, in both permalink modes.
 *
 * Examples:
 * - buildRestUrl('wpsms/v1/outbox')
 * - buildRestUrl('wp-sms-two-way/v1/webhook/register')
 *
 * @param {string} restRoute - Route without the "/wp-json/" prefix (leading slash optional)
 * @returns {string} Absolute URL
 */
export function buildRestUrl(restRoute) {
  const { apiUrl } = getWpSettings()
  const origin = window.location?.origin || ''
  const route = String(restRoute || '').trim().replace(/^\/+/, '')
  if (!route) return ''

  // Use the localized `rest_url('wpsms/v1/')` shape to infer permalink mode.
  // Pretty: /wp-json/wpsms/v1/
  // Plain:  /?rest_route=/wpsms/v1/
  const base = new URL(apiUrl || '/', origin || 'http://localhost')

  if (base.searchParams.has('rest_route')) {
    base.searchParams.set('rest_route', `/${route}`)
    return base.toString()
  }

  const marker = '/wp-json/'
  const idx = base.pathname.indexOf(marker)
  if (idx !== -1) {
    const rootPath = base.pathname.slice(0, idx + marker.length)
    const normalized = rootPath.replace(/\/+$/, '/')
    base.pathname = normalized + route
    base.search = '' // Drop any accidental query string in pretty mode.
    return base.toString()
  }

  // Last-resort: join route to whatever pathname we have.
  base.pathname = base.pathname.replace(/\/+$/, '/') + route
  return base.toString()
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

