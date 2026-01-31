import { useState, useCallback, useEffect, useRef, useMemo } from 'react'

/**
 * Custom hook for managing filter state with debouncing
 * Consolidates: search, status filter, group filter, and other filters with debounce
 *
 * @param {Object} initialFilters - Initial filter values
 * @param {Object} options - Hook options
 * @param {number} options.debounceMs - Debounce delay in ms (default: 500)
 * @param {Function} options.onChange - Callback when debounced filters change
 * @returns {Object} Filter state and methods
 */
export function useFilters(initialFilters = {}, { debounceMs = 500, onChange } = {}) {
  const [filters, setFiltersState] = useState(initialFilters)
  const [debouncedFilters, setDebouncedFilters] = useState(initialFilters)
  const timerRef = useRef(null)
  const onChangeRef = useRef(onChange)

  // Keep onChange ref updated
  useEffect(() => {
    onChangeRef.current = onChange
  }, [onChange])

  /**
   * Clear debounce timer
   */
  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
  }, [])

  /**
   * Set a single filter value
   * @param {string} key - Filter key
   * @param {any} value - Filter value
   */
  const setFilter = useCallback(
    (key, value) => {
      setFiltersState((prev) => {
        const next = { ...prev, [key]: value }
        return next
      })
    },
    []
  )

  /**
   * Set multiple filter values
   * @param {Object} updates - Filter updates
   */
  const setFilters = useCallback((updates) => {
    setFiltersState((prev) => ({ ...prev, ...updates }))
  }, [])

  /**
   * Reset filters to initial values
   */
  const resetFilters = useCallback(() => {
    clearTimer()
    setFiltersState(initialFilters)
    setDebouncedFilters(initialFilters)
  }, [initialFilters, clearTimer])

  /**
   * Reset a single filter to its initial value
   * @param {string} key - Filter key
   */
  const resetFilter = useCallback(
    (key) => {
      setFiltersState((prev) => ({
        ...prev,
        [key]: initialFilters[key],
      }))
    },
    [initialFilters]
  )

  /**
   * Check if any filter has changed from initial value
   */
  const hasActiveFilters = useMemo(() => {
    return Object.keys(filters).some((key) => {
      const currentValue = filters[key]
      const initialValue = initialFilters[key]
      // Handle 'all' as default value
      if (currentValue === 'all' && (initialValue === 'all' || initialValue === undefined)) {
        return false
      }
      if (currentValue === '' && (initialValue === '' || initialValue === undefined)) {
        return false
      }
      return currentValue !== initialValue
    })
  }, [filters, initialFilters])

  /**
   * Get filter params for API (excludes 'all' and empty values)
   */
  const getApiParams = useCallback(() => {
    const params = {}
    Object.entries(debouncedFilters).forEach(([key, value]) => {
      if (value !== 'all' && value !== '' && value !== null && value !== undefined) {
        params[key] = value
      }
    })
    return params
  }, [debouncedFilters])

  // Debounce filter changes
  useEffect(() => {
    clearTimer()
    timerRef.current = setTimeout(() => {
      setDebouncedFilters(filters)
    }, debounceMs)

    return () => clearTimer()
  }, [filters, debounceMs, clearTimer])

  // Call onChange when debounced filters change
  useEffect(() => {
    if (onChangeRef.current) {
      onChangeRef.current(debouncedFilters)
    }
  }, [debouncedFilters])

  // Cleanup on unmount
  useEffect(() => {
    return () => clearTimer()
  }, [clearTimer])

  return {
    // State
    filters,
    debouncedFilters,
    hasActiveFilters,

    // Setters
    setFilter,
    setFilters,
    resetFilter,
    resetFilters,

    // Utilities
    getApiParams,

    // Common filter helpers
    search: filters.search || '',
    setSearch: (value) => setFilter('search', value),
  }
}

/**
 * Preset hook for common table filters (search, status, group)
 * @param {Object} options - Hook options
 */
export function useTableFilters(options = {}) {
  const {
    debounceMs = 500,
    onChange,
    initialSearch = '',
    initialStatus = 'all',
    initialGroup = 'all',
  } = options

  return useFilters(
    {
      search: initialSearch,
      status: initialStatus,
      group_id: initialGroup,
    },
    { debounceMs, onChange }
  )
}

export default useFilters
