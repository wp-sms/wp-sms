import { useState, useCallback, useEffect, useRef } from 'react'

/**
 * Custom hook for managing data table state
 * Consolidates: isLoading, initialLoadDone, pagination, selectedIds, data, stats
 *
 * @param {Object} options - Hook options
 * @param {Function} options.fetchFn - Async function to fetch data. Receives (params) => Promise<{items, pagination, stats?}>
 * @param {number} options.initialPerPage - Initial items per page (default: 20)
 * @param {Function} options.getRowId - Function to get row ID (default: row => row.id)
 * @param {boolean} options.fetchOnMount - Whether to fetch on mount (default: true)
 * @returns {Object} Data table state and methods
 */
export function useDataTable({
  fetchFn,
  initialPerPage = 20,
  getRowId = (row) => row.id,
  fetchOnMount = true,
} = {}) {
  // Data state
  const [data, setData] = useState([])
  const [pagination, setPagination] = useState({
    total: 0,
    total_pages: 1,
    current_page: 1,
    per_page: initialPerPage,
  })
  const [stats, setStats] = useState(null)

  // UI state
  const [isLoading, setIsLoading] = useState(true)
  const [initialLoadDone, setInitialLoadDone] = useState(false)
  const [selectedIds, setSelectedIds] = useState([])
  const [error, setError] = useState(null)

  // Track current request to avoid race conditions
  const requestRef = useRef(0)

  /**
   * Fetch data with given parameters
   * @param {Object} params - Fetch parameters (page, filters, etc.)
   */
  const fetch = useCallback(
    async (params = {}) => {
      if (!fetchFn) return

      const currentRequest = ++requestRef.current
      setIsLoading(true)
      setError(null)

      try {
        const page = params.page ?? pagination.current_page
        const result = await fetchFn({
          page,
          per_page: pagination.per_page,
          ...params,
        })

        // Ignore stale responses
        if (currentRequest !== requestRef.current) return

        setData(result.items || [])
        setPagination(result.pagination || pagination)
        if (result.stats) {
          setStats(result.stats)
        }
      } catch (err) {
        if (currentRequest !== requestRef.current) return
        setError(err.message || 'Failed to fetch data')
      } finally {
        if (currentRequest === requestRef.current) {
          setIsLoading(false)
          setInitialLoadDone(true)
        }
      }
    },
    [fetchFn, pagination.current_page, pagination.per_page]
  )

  /**
   * Refresh data (re-fetch current page)
   */
  const refresh = useCallback(() => {
    return fetch({ page: pagination.current_page })
  }, [fetch, pagination.current_page])

  /**
   * Handle page change
   * @param {number} page - Page number
   */
  const handlePageChange = useCallback(
    (page) => {
      fetch({ page })
    },
    [fetch]
  )

  /**
   * Handle per page change
   * @param {number} perPage - Items per page
   */
  const handlePerPageChange = useCallback(
    (perPage) => {
      setPagination((prev) => ({ ...prev, per_page: perPage }))
      fetch({ page: 1, per_page: perPage })
    },
    [fetch]
  )

  /**
   * Select/deselect a row
   * @param {string|number} id - Row ID
   */
  const toggleSelection = useCallback(
    (id) => {
      setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]))
    },
    []
  )

  /**
   * Select/deselect all rows
   */
  const toggleSelectAll = useCallback(() => {
    if (selectedIds.length === data.length) {
      setSelectedIds([])
    } else {
      setSelectedIds(data.map(getRowId))
    }
  }, [selectedIds.length, data, getRowId])

  /**
   * Clear selection
   */
  const clearSelection = useCallback(() => {
    setSelectedIds([])
  }, [])

  /**
   * Check if a row is selected
   * @param {string|number} id - Row ID
   */
  const isSelected = useCallback(
    (id) => {
      return selectedIds.includes(id)
    },
    [selectedIds]
  )

  /**
   * Update a single item in the data array
   * @param {string|number} id - Row ID
   * @param {Object} updates - Updates to apply
   */
  const updateItem = useCallback(
    (id, updates) => {
      setData((prev) =>
        prev.map((item) => (getRowId(item) === id ? { ...item, ...updates } : item))
      )
    },
    [getRowId]
  )

  /**
   * Remove items from data array
   * @param {Array<string|number>} ids - Row IDs to remove
   */
  const removeItems = useCallback(
    (ids) => {
      setData((prev) => prev.filter((item) => !ids.includes(getRowId(item))))
      setSelectedIds((prev) => prev.filter((id) => !ids.includes(id)))
      // Update pagination
      setPagination((prev) => ({
        ...prev,
        total: Math.max(0, prev.total - ids.length),
      }))
    },
    [getRowId]
  )

  /**
   * Add an item to the data array (prepends by default)
   * @param {Object} item - Item to add
   * @param {boolean} prepend - Whether to prepend (default: true)
   */
  const addItem = useCallback((item, prepend = true) => {
    setData((prev) => (prepend ? [item, ...prev] : [...prev, item]))
    setPagination((prev) => ({
      ...prev,
      total: prev.total + 1,
    }))
  }, [])

  // Initial fetch on mount
  useEffect(() => {
    if (fetchOnMount) {
      fetch({ page: 1 })
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  return {
    // Data
    data,
    setData,
    pagination,
    stats,
    error,

    // Loading states
    isLoading,
    initialLoadDone,

    // Selection
    selectedIds,
    setSelectedIds,
    toggleSelection,
    toggleSelectAll,
    clearSelection,
    isSelected,
    allSelected: data.length > 0 && selectedIds.length === data.length,
    someSelected: selectedIds.length > 0 && selectedIds.length < data.length,

    // Actions
    fetch,
    refresh,
    handlePageChange,
    handlePerPageChange,
    updateItem,
    removeItems,
    addItem,
  }
}

export default useDataTable
