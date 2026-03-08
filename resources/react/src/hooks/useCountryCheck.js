import { useCallback } from 'react'
import { useSettings } from '@/context/SettingsContext'
import { __ } from '@/lib/utils'

/**
 * Hook to filter phone numbers against country restrictions.
 *
 * @returns {Function} filterByCountry(numbers) - returns { allowed: string[], blocked: string[] }
 *   If the setting is disabled, all numbers are returned as allowed.
 */
export function useCountryCheck() {
  const { getSetting } = useSettings()

  return useCallback((numbers) => {
    const list = Array.isArray(numbers)
      ? numbers
      : String(numbers).split(',').map(n => n.trim()).filter(Boolean)

    const localOnly = getSetting('send_only_local_numbers', '')
    const allowedCountries = getSetting('only_local_numbers_countries', [])

    if (!localOnly || !Array.isArray(allowedCountries) || allowedCountries.length === 0) {
      return { allowed: list, blocked: [] }
    }

    const allowed = []
    const blocked = []

    for (const number of list) {
      if (allowedCountries.some(code => number.startsWith(code))) {
        allowed.push(number)
      } else {
        blocked.push(number)
      }
    }

    return { allowed, blocked }
  }, [getSetting])
}
