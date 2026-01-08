import { useCallback, useEffect } from 'react'
import { useDataTable } from './useDataTable'
import { useFilters } from './useFilters'
import { useToast } from '@/components/ui/toaster'
import { __ } from '@/lib/utils'

/**
 * Combined hook for list pages that handles:
 * - Data fetching with useDataTable
 * - Filtering with useFilters (debounced)
 * - Auto-refetch when filters change
 * - Standard delete and bulk action handlers with toast notifications
 *
 * @param {Object} options - Hook options
 * @param {Function} options.fetchFn - Async function to fetch data. Receives (params) => Promise<{items, pagination, stats?}>
 * @param {Function} options.deleteFn - Async function to delete single item. Receives (id) => Promise
 * @param {Function} options.bulkActionFn - Async function for bulk actions. Receives (action, ids) => Promise
 * @param {Object} options.initialFilters - Initial filter values (default: { search: '' })
 * @param {number} options.perPage - Items per page (default: 20)
 * @param {number} options.debounceMs - Filter debounce delay in ms (default: 500)
 * @param {Function} options.getRowId - Function to get row ID (default: row => row.id)
 * @param {Object} options.messages - Custom success messages
 * @returns {Object} Combined list page state and methods
 *
 * @example
 * const { filters, table, handleDelete, handleBulkAction } = useListPage({
 *   fetchFn: (params) => subscribersApi.getSubscribers(params),
 *   deleteFn: (id) => subscribersApi.deleteSubscriber(id),
 *   bulkActionFn: (action, ids) => subscribersApi.bulkAction(action, ids),
 *   initialFilters: { search: '', status: 'all', group_id: 'all' },
 * })
 */
export function useListPage({
  fetchFn,
  deleteFn,
  bulkActionFn,
  initialFilters = { search: '' },
  perPage = 20,
  debounceMs = 500,
  getRowId = (row) => row.id,
  messages = {},
} = {}) {
  const { toast } = useToast()

  // Default messages
  const {
    deleteSuccess = __('Deleted successfully'),
    deleteError = __('Failed to delete'),
    bulkSuccess = __('Action completed successfully'),
    bulkError = __('Action failed'),
    noSelection = __('No items selected'),
  } = messages

  // Initialize filters with debouncing
  const filters = useFilters(initialFilters, { debounceMs })

  // Create fetch function that incorporates debounced filters
  const fetchWithFilters = useCallback(
    async (params = {}) => {
      // Get clean API params (excludes 'all' and empty values)
      const filterParams = filters.getApiParams()
      return fetchFn({
        ...filterParams,
        ...params,
      })
    },
    [fetchFn, filters.debouncedFilters] // Re-create when debounced filters change
  )

  // Initialize data table
  const table = useDataTable({
    fetchFn: fetchWithFilters,
    initialPerPage: perPage,
    getRowId,
  })

  // Auto-refetch when debounced filters change (after initial load)
  useEffect(() => {
    if (table.initialLoadDone) {
      table.fetch({ page: 1 })
    }
  }, [filters.debouncedFilters]) // eslint-disable-line react-hooks/exhaustive-deps

  /**
   * Handle single item deletion
   * @param {string|number} id - Item ID to delete
   * @param {Object} options - Options
   * @param {boolean} options.optimistic - Remove from UI before API call (default: false)
   */
  const handleDelete = useCallback(
    async (id, { optimistic = false } = {}) => {
      if (!deleteFn) {
        console.warn('useListPage: deleteFn not provided')
        return
      }

      try {
        if (optimistic) {
          table.removeItems([id])
        }

        await deleteFn(id)

        if (!optimistic) {
          table.removeItems([id])
        }

        toast({
          title: deleteSuccess,
          variant: 'success',
        })
      } catch (error) {
        // If optimistic, we need to refresh to restore the item
        if (optimistic) {
          table.refresh()
        }

        toast({
          title: error.message || deleteError,
          variant: 'destructive',
        })
        throw error
      }
    },
    [deleteFn, table, toast, deleteSuccess, deleteError]
  )

  /**
   * Handle bulk action on selected items
   * @param {string} action - Action name (e.g., 'delete', 'activate', 'deactivate')
   * @param {Object} options - Options
   * @param {Array} options.ids - Specific IDs to act on (defaults to selectedIds)
   * @param {boolean} options.clearOnSuccess - Clear selection after success (default: true)
   * @param {boolean} options.refreshOnSuccess - Refresh table after success (default: true)
   */
  const handleBulkAction = useCallback(
    async (action, { ids, clearOnSuccess = true, refreshOnSuccess = true } = {}) => {
      const targetIds = ids || table.selectedIds

      if (!bulkActionFn) {
        console.warn('useListPage: bulkActionFn not provided')
        return
      }

      if (targetIds.length === 0) {
        toast({
          title: noSelection,
          variant: 'warning',
        })
        return
      }

      try {
        await bulkActionFn(action, targetIds)

        if (clearOnSuccess) {
          table.clearSelection()
        }

        if (refreshOnSuccess) {
          table.refresh()
        }

        toast({
          title: bulkSuccess,
          variant: 'success',
        })
      } catch (error) {
        toast({
          title: error.message || bulkError,
          variant: 'destructive',
        })
        throw error
      }
    },
    [bulkActionFn, table, toast, noSelection, bulkSuccess, bulkError]
  )

  /**
   * Convenience method to handle bulk delete
   */
  const handleBulkDelete = useCallback(
    (options = {}) => handleBulkAction('delete', options),
    [handleBulkAction]
  )

  return {
    // Filter state and methods
    filters,

    // Table state and methods
    table,

    // Standard action handlers
    handleDelete,
    handleBulkAction,
    handleBulkDelete,

    // Convenience properties
    isLoading: table.isLoading,
    isEmpty: !table.isLoading && table.data.length === 0,
    hasSelection: table.selectedIds.length > 0,
    selectionCount: table.selectedIds.length,
  }
}

export default useListPage
