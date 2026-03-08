import { renderHook, act, waitFor } from '@testing-library/react'
import { useListPage } from '@/hooks/useListPage'

// Mock the useToast hook
const mockToast = jest.fn()
jest.mock('../components/ui/toaster', () => ({
  useToast: () => ({ toast: mockToast }),
}))

describe('useListPage', () => {
  const mockFetchFn = jest.fn()
  const mockDeleteFn = jest.fn()
  const mockBulkActionFn = jest.fn()

  const defaultOptions = {
    fetchFn: mockFetchFn,
    deleteFn: mockDeleteFn,
    bulkActionFn: mockBulkActionFn,
    initialFilters: { search: '', status: 'all' },
  }

  beforeEach(() => {
    jest.clearAllMocks()
    mockFetchFn.mockResolvedValue({
      items: [
        { id: 1, name: 'Item 1' },
        { id: 2, name: 'Item 2' },
      ],
      pagination: { total: 2, total_pages: 1, current_page: 1, per_page: 20 },
    })
  })

  describe('initialization', () => {
    test('initializes filters with initial values', async () => {
      const { result } = renderHook(() => useListPage(defaultOptions))

      expect(result.current.filters.filters).toEqual({ search: '', status: 'all' })
    })

    test('fetches data on mount', async () => {
      renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(mockFetchFn).toHaveBeenCalled()
      })
    })

    test('provides table state', async () => {
      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      expect(result.current.table.pagination).toBeDefined()
      expect(result.current.table.selectedIds).toEqual([])
    })
  })

  describe('convenience properties', () => {
    test('isLoading reflects table loading state', async () => {
      const { result } = renderHook(() => useListPage(defaultOptions))

      // Initially loading
      expect(result.current.isLoading).toBe(true)

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false)
      })
    })

    test('isEmpty is true when no data', async () => {
      mockFetchFn.mockResolvedValue({
        items: [],
        pagination: { total: 0, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.isEmpty).toBe(true)
      })
    })

    test('hasSelection reflects selection state', async () => {
      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      expect(result.current.hasSelection).toBe(false)

      act(() => {
        result.current.table.toggleSelection(1)
      })

      expect(result.current.hasSelection).toBe(true)
      expect(result.current.selectionCount).toBe(1)
    })
  })

  describe('handleDelete', () => {
    test('deletes item and shows success toast', async () => {
      mockDeleteFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      await act(async () => {
        await result.current.handleDelete(1)
      })

      expect(mockDeleteFn).toHaveBeenCalledWith(1)
      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          variant: 'success',
        })
      )
    })

    test('shows error toast on delete failure', async () => {
      mockDeleteFn.mockRejectedValue(new Error('Delete failed'))

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      await expect(
        act(async () => {
          await result.current.handleDelete(1)
        })
      ).rejects.toThrow('Delete failed')

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Delete failed',
          variant: 'destructive',
        })
      )
    })

    test('removes item from table on success', async () => {
      mockDeleteFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      await act(async () => {
        await result.current.handleDelete(1)
      })

      expect(result.current.table.data).toHaveLength(1)
      expect(result.current.table.data[0].id).toBe(2)
    })

    test('warns if deleteFn not provided', async () => {
      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation()

      const { result } = renderHook(() =>
        useListPage({ ...defaultOptions, deleteFn: undefined })
      )

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      await act(async () => {
        await result.current.handleDelete(1)
      })

      expect(consoleSpy).toHaveBeenCalledWith('useListPage: deleteFn not provided')
      consoleSpy.mockRestore()
    })
  })

  describe('handleBulkAction', () => {
    test('executes bulk action on selected items', async () => {
      mockBulkActionFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      // Select items
      act(() => {
        result.current.table.toggleSelection(1)
        result.current.table.toggleSelection(2)
      })

      await act(async () => {
        await result.current.handleBulkAction('delete')
      })

      expect(mockBulkActionFn).toHaveBeenCalledWith('delete', [1, 2])
      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          variant: 'success',
        })
      )
    })

    test('clears selection after bulk action', async () => {
      mockBulkActionFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      act(() => {
        result.current.table.toggleSelection(1)
      })

      expect(result.current.table.selectedIds).toHaveLength(1)

      await act(async () => {
        await result.current.handleBulkAction('delete')
      })

      expect(result.current.table.selectedIds).toHaveLength(0)
    })

    test('shows warning when no items selected', async () => {
      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      await act(async () => {
        await result.current.handleBulkAction('delete')
      })

      expect(mockBulkActionFn).not.toHaveBeenCalled()
      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          variant: 'warning',
        })
      )
    })

    test('shows error toast on bulk action failure', async () => {
      mockBulkActionFn.mockRejectedValue(new Error('Bulk action failed'))

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      act(() => {
        result.current.table.toggleSelection(1)
      })

      await expect(
        act(async () => {
          await result.current.handleBulkAction('delete')
        })
      ).rejects.toThrow('Bulk action failed')

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Bulk action failed',
          variant: 'destructive',
        })
      )
    })

    test('handleBulkDelete is convenience method for delete action', async () => {
      mockBulkActionFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      act(() => {
        result.current.table.toggleSelection(1)
      })

      await act(async () => {
        await result.current.handleBulkDelete()
      })

      expect(mockBulkActionFn).toHaveBeenCalledWith('delete', [1])
    })
  })

  describe('filter integration', () => {
    test('refetches when filters change', async () => {
      jest.useFakeTimers()

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.initialLoadDone).toBe(true)
      })

      mockFetchFn.mockClear()

      // Change a filter
      act(() => {
        result.current.filters.setFilter('search', 'test')
      })

      // Advance past debounce
      act(() => {
        jest.advanceTimersByTime(600)
      })

      await waitFor(() => {
        expect(mockFetchFn).toHaveBeenCalled()
      })

      jest.useRealTimers()
    })

    test('passes filter values to fetch function', async () => {
      jest.useFakeTimers()

      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.initialLoadDone).toBe(true)
      })

      mockFetchFn.mockClear()

      act(() => {
        result.current.filters.setFilter('status', 'active')
      })

      act(() => {
        jest.advanceTimersByTime(600)
      })

      await waitFor(() => {
        expect(mockFetchFn).toHaveBeenCalledWith(
          expect.objectContaining({
            status: 'active',
          })
        )
      })

      jest.useRealTimers()
    })

    test('excludes "all" values from fetch params', async () => {
      const { result } = renderHook(() => useListPage(defaultOptions))

      await waitFor(() => {
        expect(result.current.table.initialLoadDone).toBe(true)
      })

      // Initial call should not include 'all' status
      expect(mockFetchFn).toHaveBeenCalledWith(
        expect.not.objectContaining({
          status: 'all',
        })
      )
    })
  })

  describe('custom messages', () => {
    test('uses custom delete success message', async () => {
      mockDeleteFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() =>
        useListPage({
          ...defaultOptions,
          messages: { deleteSuccess: 'Item removed!' },
        })
      )

      await waitFor(() => {
        expect(result.current.table.data).toHaveLength(2)
      })

      await act(async () => {
        await result.current.handleDelete(1)
      })

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Item removed!',
        })
      )
    })
  })
})
