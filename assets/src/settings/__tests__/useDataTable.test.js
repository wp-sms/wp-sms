import { renderHook, act, waitFor } from '@testing-library/react'
import { useDataTable } from '../hooks/useDataTable'

describe('useDataTable', () => {
  const mockFetchFn = jest.fn()

  beforeEach(() => {
    jest.clearAllMocks()
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  describe('initialization', () => {
    test('initializes with default values', () => {
      const { result } = renderHook(() =>
        useDataTable({ fetchFn: mockFetchFn, fetchOnMount: false })
      )

      expect(result.current.data).toEqual([])
      expect(result.current.pagination).toEqual({
        total: 0,
        total_pages: 1,
        current_page: 1,
        per_page: 20,
      })
      expect(result.current.isLoading).toBe(true)
      expect(result.current.initialLoadDone).toBe(false)
      expect(result.current.selectedIds).toEqual([])
    })

    test('uses custom initialPerPage', () => {
      const { result } = renderHook(() =>
        useDataTable({ fetchFn: mockFetchFn, initialPerPage: 50, fetchOnMount: false })
      )

      expect(result.current.pagination.per_page).toBe(50)
    })
  })

  describe('fetching', () => {
    test('fetches on mount when fetchOnMount is true', async () => {
      mockFetchFn.mockResolvedValue({
        items: [{ id: 1, name: 'Test' }],
        pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
      })

      jest.useRealTimers()
      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.initialLoadDone).toBe(true)
      })

      expect(mockFetchFn).toHaveBeenCalledWith({ page: 1, per_page: 20 })
      expect(result.current.data).toEqual([{ id: 1, name: 'Test' }])
      expect(result.current.isLoading).toBe(false)
    })

    test('does not fetch on mount when fetchOnMount is false', () => {
      renderHook(() => useDataTable({ fetchFn: mockFetchFn, fetchOnMount: false }))

      expect(mockFetchFn).not.toHaveBeenCalled()
    })

    test('handles fetch error', async () => {
      mockFetchFn.mockRejectedValue(new Error('Fetch failed'))

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.error).toBe('Fetch failed')
      })

      expect(result.current.isLoading).toBe(false)
      expect(result.current.initialLoadDone).toBe(true)
    })

    test('updates stats when provided', async () => {
      mockFetchFn.mockResolvedValue({
        items: [],
        pagination: { total: 10, total_pages: 1, current_page: 1, per_page: 20 },
        stats: { total: 10, active: 8, inactive: 2 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.stats).toEqual({ total: 10, active: 8, inactive: 2 })
      })
    })
  })

  describe('selection', () => {
    test('toggles single selection', async () => {
      mockFetchFn.mockResolvedValue({
        items: [
          { id: 1, name: 'Test 1' },
          { id: 2, name: 'Test 2' },
        ],
        pagination: { total: 2, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.data.length).toBe(2)
      })

      act(() => {
        result.current.toggleSelection(1)
      })

      expect(result.current.selectedIds).toEqual([1])
      expect(result.current.isSelected(1)).toBe(true)
      expect(result.current.isSelected(2)).toBe(false)

      act(() => {
        result.current.toggleSelection(1)
      })

      expect(result.current.selectedIds).toEqual([])
    })

    test('toggles select all', async () => {
      mockFetchFn.mockResolvedValue({
        items: [
          { id: 1, name: 'Test 1' },
          { id: 2, name: 'Test 2' },
        ],
        pagination: { total: 2, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.data.length).toBe(2)
      })

      act(() => {
        result.current.toggleSelectAll()
      })

      expect(result.current.selectedIds).toEqual([1, 2])
      expect(result.current.allSelected).toBe(true)

      act(() => {
        result.current.toggleSelectAll()
      })

      expect(result.current.selectedIds).toEqual([])
      expect(result.current.allSelected).toBe(false)
    })

    test('clears selection', async () => {
      mockFetchFn.mockResolvedValue({
        items: [{ id: 1 }],
        pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.data.length).toBe(1)
      })

      act(() => {
        result.current.toggleSelection(1)
      })

      expect(result.current.selectedIds).toEqual([1])

      act(() => {
        result.current.clearSelection()
      })

      expect(result.current.selectedIds).toEqual([])
    })
  })

  describe('data manipulation', () => {
    test('updates an item', async () => {
      mockFetchFn.mockResolvedValue({
        items: [{ id: 1, name: 'Original' }],
        pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.data[0].name).toBe('Original')
      })

      act(() => {
        result.current.updateItem(1, { name: 'Updated' })
      })

      expect(result.current.data[0].name).toBe('Updated')
    })

    test('removes items', async () => {
      mockFetchFn.mockResolvedValue({
        items: [
          { id: 1, name: 'Test 1' },
          { id: 2, name: 'Test 2' },
        ],
        pagination: { total: 2, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.data.length).toBe(2)
      })

      // Select item before removing
      act(() => {
        result.current.toggleSelection(1)
      })

      act(() => {
        result.current.removeItems([1])
      })

      expect(result.current.data).toEqual([{ id: 2, name: 'Test 2' }])
      expect(result.current.selectedIds).toEqual([])
      expect(result.current.pagination.total).toBe(1)
    })

    test('adds an item', async () => {
      mockFetchFn.mockResolvedValue({
        items: [{ id: 1, name: 'Existing' }],
        pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(result.current.data.length).toBe(1)
      })

      act(() => {
        result.current.addItem({ id: 2, name: 'New' })
      })

      expect(result.current.data[0]).toEqual({ id: 2, name: 'New' })
      expect(result.current.data.length).toBe(2)
      expect(result.current.pagination.total).toBe(2)
    })
  })

  describe('pagination', () => {
    test('handles page change', async () => {
      mockFetchFn.mockResolvedValue({
        items: [],
        pagination: { total: 50, total_pages: 3, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(mockFetchFn).toHaveBeenCalled()
      })

      mockFetchFn.mockClear()

      await act(async () => {
        result.current.handlePageChange(2)
      })

      expect(mockFetchFn).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }))
    })

    test('handles per page change', async () => {
      mockFetchFn.mockResolvedValue({
        items: [],
        pagination: { total: 50, total_pages: 3, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() => useDataTable({ fetchFn: mockFetchFn }))

      await waitFor(() => {
        expect(mockFetchFn).toHaveBeenCalled()
      })

      mockFetchFn.mockClear()

      await act(async () => {
        result.current.handlePerPageChange(50)
      })

      expect(mockFetchFn).toHaveBeenCalledWith(expect.objectContaining({ page: 1, per_page: 50 }))
    })
  })

  describe('custom getRowId', () => {
    test('uses custom getRowId function', async () => {
      mockFetchFn.mockResolvedValue({
        items: [{ customId: 'abc', name: 'Test' }],
        pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
      })

      const { result } = renderHook(() =>
        useDataTable({
          fetchFn: mockFetchFn,
          getRowId: (row) => row.customId,
        })
      )

      await waitFor(() => {
        expect(result.current.data.length).toBe(1)
      })

      act(() => {
        result.current.toggleSelection('abc')
      })

      expect(result.current.selectedIds).toEqual(['abc'])
      expect(result.current.isSelected('abc')).toBe(true)
    })
  })
})
