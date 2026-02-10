/**
 * PHP-to-JavaScript Date Formatter
 *
 * Interprets PHP date format characters and formats dates accordingly.
 * Uses Intl.DateTimeFormat internally for locale-aware month/day names.
 */

/**
 * Get the ordinal suffix for a day number (1st, 2nd, 3rd, etc.)
 * @param {number} day - Day of month
 * @returns {string} Ordinal suffix
 */
function getOrdinalSuffix(day) {
  if (day >= 11 && day <= 13) {
    return 'th'
  }
  switch (day % 10) {
    case 1:
      return 'st'
    case 2:
      return 'nd'
    case 3:
      return 'rd'
    default:
      return 'th'
  }
}

/**
 * Pad a number with leading zeros
 * @param {number} num - Number to pad
 * @param {number} length - Desired length
 * @returns {string} Padded number
 */
function padZero(num, length = 2) {
  return String(num).padStart(length, '0')
}

/**
 * Get locale-aware day/month names using Intl.DateTimeFormat
 * @param {Date} date - Date object
 * @param {string} type - 'weekday' or 'month'
 * @param {string} style - 'long' or 'short'
 * @param {string} locale - Locale string (defaults to browser locale)
 * @returns {string} Formatted name
 */
function getLocalizedName(date, type, style, locale = undefined) {
  const options = { [type]: style }
  return new Intl.DateTimeFormat(locale, options).format(date)
}

/**
 * Format a date according to PHP date format string
 * @param {Date} date - Date object to format
 * @param {string} format - PHP date format string
 * @param {string} timezone - Timezone string (e.g., 'America/New_York')
 * @returns {string} Formatted date string
 */
function formatWithPHPFormat(date, format, timezone) {
  // Convert to timezone if provided
  let targetDate = date
  if (timezone) {
    try {
      // Get the date parts in the target timezone
      const options = {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
      }
      const formatter = new Intl.DateTimeFormat('en-US', options)
      const parts = formatter.formatToParts(date)
      const getPart = (type) => parts.find((p) => p.type === type)?.value || ''

      targetDate = new Date(
        parseInt(getPart('year')),
        parseInt(getPart('month')) - 1,
        parseInt(getPart('day')),
        parseInt(getPart('hour')),
        parseInt(getPart('minute')),
        parseInt(getPart('second'))
      )
    } catch {
      // Fall back to original date if timezone conversion fails
      targetDate = date
    }
  }

  const year = targetDate.getFullYear()
  const month = targetDate.getMonth() // 0-indexed
  const day = targetDate.getDate()
  const hours24 = targetDate.getHours()
  const hours12 = hours24 % 12 || 12
  const minutes = targetDate.getMinutes()
  const seconds = targetDate.getSeconds()
  const dayOfWeek = targetDate.getDay() // 0 = Sunday

  // Map PHP format characters to their values
  const formatMap = {
    // Day
    d: () => padZero(day), // Day of month, 2 digits with leading zeros (01-31)
    D: () => getLocalizedName(targetDate, 'weekday', 'short'), // Short day name (Mon-Sun)
    j: () => String(day), // Day of month without leading zeros (1-31)
    l: () => getLocalizedName(targetDate, 'weekday', 'long'), // Full day name (Monday-Sunday)
    N: () => String(dayOfWeek === 0 ? 7 : dayOfWeek), // ISO-8601 day of week (1=Monday, 7=Sunday)
    S: () => getOrdinalSuffix(day), // Ordinal suffix (st, nd, rd, th)
    w: () => String(dayOfWeek), // Day of week (0=Sunday, 6=Saturday)

    // Month
    F: () => getLocalizedName(targetDate, 'month', 'long'), // Full month name (January-December)
    m: () => padZero(month + 1), // Month with leading zeros (01-12)
    M: () => getLocalizedName(targetDate, 'month', 'short'), // Short month name (Jan-Dec)
    n: () => String(month + 1), // Month without leading zeros (1-12)
    t: () => String(new Date(year, month + 1, 0).getDate()), // Days in month (28-31)

    // Year
    Y: () => String(year), // Full 4-digit year (e.g., 2026)
    y: () => String(year).slice(-2), // 2-digit year (e.g., 26)
    L: () => (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0) ? '1' : '0'), // Leap year (1 or 0)

    // Time
    a: () => (hours24 < 12 ? 'am' : 'pm'), // Lowercase am/pm
    A: () => (hours24 < 12 ? 'AM' : 'PM'), // Uppercase AM/PM
    g: () => String(hours12), // 12-hour without leading zeros (1-12)
    G: () => String(hours24), // 24-hour without leading zeros (0-23)
    h: () => padZero(hours12), // 12-hour with leading zeros (01-12)
    H: () => padZero(hours24), // 24-hour with leading zeros (00-23)
    i: () => padZero(minutes), // Minutes with leading zeros (00-59)
    s: () => padZero(seconds), // Seconds with leading zeros (00-59)
  }

  let result = ''
  let escaped = false

  for (let i = 0; i < format.length; i++) {
    const char = format[i]

    // Handle escape character
    if (char === '\\' && !escaped) {
      escaped = true
      continue
    }

    if (escaped) {
      result += char
      escaped = false
      continue
    }

    // Check if this is a format character
    if (formatMap[char]) {
      result += formatMap[char]()
    } else {
      result += char
    }
  }

  return result
}

/**
 * Format a date using WordPress date/time format settings
 * @param {string|Date} date - Date to format (string or Date object)
 * @param {object} options - Formatting options
 * @param {boolean} options.includeTime - Whether to include time (default: false)
 * @param {string} options.dateFormat - PHP date format string (default: 'F j, Y')
 * @param {string} options.timeFormat - PHP time format string (default: 'g:i a')
 * @param {string} options.timezone - Timezone string
 * @returns {string} Formatted date string
 */
export function formatDateWP(date, options = {}) {
  const {
    includeTime = false,
    dateFormat = 'F j, Y',
    timeFormat = 'g:i a',
    timezone = undefined,
  } = options

  // Parse the date
  const dateObj = date instanceof Date ? date : new Date(date)

  // Check for invalid date
  if (isNaN(dateObj.getTime())) {
    return ''
  }

  // Build the format string
  let format = dateFormat
  if (includeTime) {
    format += ', ' + timeFormat
  }

  return formatWithPHPFormat(dateObj, format, timezone)
}

export default formatDateWP
