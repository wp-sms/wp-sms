import { renderHook, act } from '@testing-library/react'
import { useFilters, useTableFilters } from '../hooks/useFilters'

describe('useFilters', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  describe('initialization', () => {
    test('initializes with provided filters', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all', group: 'all' })
      )

      expect(result.current.filters).toEqual({
        search: '',
        status: 'all',
        group: 'all',
      })
    })

    test('initializes with empty object by default', () => {
      const { result } = renderHook(() => useFilters())

      expect(result.current.filters).toEqual({})
    })
  })

  describe('setFilter', () => {
    test('sets a single filter value', () => {
      const { result } = renderHook(() => useFilters({ search: '' }))

      act(() => {
        result.current.setFilter('search', 'test')
      })

      expect(result.current.filters.search).toBe('test')
    })

    test('adds new filter key', () => {
      const { result } = renderHook(() => useFilters({ search: '' }))

      act(() => {
        result.current.setFilter('status', 'active')
      })

      expect(result.current.filters).toEqual({
        search: '',
        status: 'active',
      })
    })
  })

  describe('setFilters', () => {
    test('sets multiple filter values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all' })
      )

      act(() => {
        result.current.setFilters({ search: 'test', status: 'active' })
      })

      expect(result.current.filters).toEqual({
        search: 'test',
        status: 'active',
      })
    })

    test('preserves unchanged filters', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all', group: 'all' })
      )

      act(() => {
        result.current.setFilters({ search: 'test' })
      })

      expect(result.current.filters).toEqual({
        search: 'test',
        status: 'all',
        group: 'all',
      })
    })
  })

  describe('debouncing', () => {
    test('debounces filter changes', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '' }, { debounceMs: 500 })
      )

      act(() => {
        result.current.setFilter('search', 'test')
      })

      // Not debounced yet
      expect(result.current.debouncedFilters.search).toBe('')

      act(() => {
        jest.advanceTimersByTime(500)
      })

      // Now debounced
      expect(result.current.debouncedFilters.search).toBe('test')
    })

    test('resets debounce on rapid changes', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '' }, { debounceMs: 500 })
      )

      act(() => {
        result.current.setFilter('search', 'a')
      })

      act(() => {
        jest.advanceTimersByTime(300)
      })

      act(() => {
        result.current.setFilter('search', 'ab')
      })

      act(() => {
        jest.advanceTimersByTime(300)
      })

      // Still waiting for debounce
      expect(result.current.debouncedFilters.search).toBe('')

      act(() => {
        jest.advanceTimersByTime(200)
      })

      // Now debounced with final value
      expect(result.current.debouncedFilters.search).toBe('ab')
    })

    test('calls onChange callback after debounce', () => {
      const onChange = jest.fn()
      const { result } = renderHook(() =>
        useFilters({ search: '' }, { debounceMs: 500, onChange })
      )

      // Initial debounce completes
      act(() => {
        jest.advanceTimersByTime(500)
      })

      // Clear initial call
      onChange.mockClear()

      act(() => {
        result.current.setFilter('search', 'test')
      })

      expect(onChange).not.toHaveBeenCalled()

      act(() => {
        jest.advanceTimersByTime(500)
      })

      expect(onChange).toHaveBeenCalledWith({ search: 'test' })
    })
  })

  describe('resetFilters', () => {
    test('resets to initial values', () => {
      const initialFilters = { search: '', status: 'all' }
      const { result } = renderHook(() => useFilters(initialFilters))

      act(() => {
        result.current.setFilters({ search: 'test', status: 'active' })
      })

      act(() => {
        jest.advanceTimersByTime(500)
      })

      act(() => {
        result.current.resetFilters()
      })

      expect(result.current.filters).toEqual(initialFilters)
      expect(result.current.debouncedFilters).toEqual(initialFilters)
    })
  })

  describe('resetFilter', () => {
    test('resets a single filter to initial value', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all' })
      )

      act(() => {
        result.current.setFilters({ search: 'test', status: 'active' })
      })

      act(() => {
        result.current.resetFilter('search')
      })

      expect(result.current.filters).toEqual({
        search: '',
        status: 'active',
      })
    })
  })

  describe('hasActiveFilters', () => {
    test('returns false when all filters are at initial values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all' })
      )

      expect(result.current.hasActiveFilters).toBe(false)
    })

    test('returns true when a filter differs from initial', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all' })
      )

      act(() => {
        result.current.setFilter('search', 'test')
      })

      expect(result.current.hasActiveFilters).toBe(true)
    })

    test('treats "all" as default for undefined initial', () => {
      const { result } = renderHook(() => useFilters({}))

      act(() => {
        result.current.setFilter('status', 'all')
      })

      expect(result.current.hasActiveFilters).toBe(false)
    })
  })

  describe('getApiParams', () => {
    test('excludes "all" values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: 'test', status: 'all' })
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      expect(result.current.getApiParams()).toEqual({ search: 'test' })
    })

    test('excludes empty string values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'active' })
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      expect(result.current.getApiParams()).toEqual({ status: 'active' })
    })

    test('excludes null and undefined values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: null, status: undefined, group: 'test' })
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      expect(result.current.getApiParams()).toEqual({ group: 'test' })
    })

    test('uses debounced values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '' }, { debounceMs: 500 })
      )

      act(() => {
        result.current.setFilter('search', 'test')
      })

      // Before debounce
      expect(result.current.getApiParams()).toEqual({})

      act(() => {
        jest.advanceTimersByTime(500)
      })

      // After debounce
      expect(result.current.getApiParams()).toEqual({ search: 'test' })
    })
  })

  describe('search helper', () => {
    test('provides search shortcut', () => {
      const { result } = renderHook(() => useFilters({ search: 'initial' }))

      expect(result.current.search).toBe('initial')

      act(() => {
        result.current.setSearch('new value')
      })

      expect(result.current.search).toBe('new value')
    })
  })
})

describe('useTableFilters', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  test('initializes with default table filter values', () => {
    const { result } = renderHook(() => useTableFilters())

    expect(result.current.filters).toEqual({
      search: '',
      status: 'all',
      group_id: 'all',
    })
  })

  test('accepts custom initial values', () => {
    const { result } = renderHook(() =>
      useTableFilters({
        initialSearch: 'test',
        initialStatus: 'active',
        initialGroup: '5',
      })
    )

    expect(result.current.filters).toEqual({
      search: 'test',
      status: 'active',
      group_id: '5',
    })
  })

  test('accepts onChange callback', () => {
    const onChange = jest.fn()
    const { result } = renderHook(() => useTableFilters({ onChange }))

    act(() => {
      result.current.setFilter('search', 'test')
    })

    act(() => {
      jest.advanceTimersByTime(500)
    })

    expect(onChange).toHaveBeenCalled()
  })
})
