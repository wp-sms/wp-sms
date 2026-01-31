import React from 'react'
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderHook } from '@testing-library/react'
import { useFilters } from '@/hooks/useFilters'
import { setupWpSmsSettings, createMockSubscriber, createMockGroup, createMockOutboxMessage } from './testing-utils'

/**
 * Filter Integration Tests
 *
 * These tests verify that filters work correctly across different pages:
 * - Subscribers: search, group_id, status, country_code
 * - Outbox: search, status, date_from, date_to
 */

describe('Filter Integration Tests', () => {
  beforeEach(() => {
    jest.useFakeTimers()
    setupWpSmsSettings({
      countries: [
        { code: 'US', name: 'United States' },
        { code: 'GB', name: 'United Kingdom' },
        { code: 'CA', name: 'Canada' },
      ],
    })
  })

  afterEach(() => {
    jest.useRealTimers()
    jest.clearAllMocks()
  })

  describe('Subscribers Page Filters', () => {
    const subscriberFilters = { search: '', group_id: 'all', status: 'all', country_code: 'all' }

    describe('Search Filter', () => {
      test('initializes search filter with empty string', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        expect(result.current.filters.search).toBe('')
        expect(result.current.debouncedFilters.search).toBe('')
      })

      test('updates search filter immediately in filters state', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('search', 'john')
        })

        expect(result.current.filters.search).toBe('john')
        // Debounced value should still be empty
        expect(result.current.debouncedFilters.search).toBe('')
      })

      test('debounces search filter value after delay', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('search', 'john doe')
        })

        // Before debounce
        expect(result.current.debouncedFilters.search).toBe('')

        act(() => {
          jest.advanceTimersByTime(500)
        })

        // After debounce
        expect(result.current.debouncedFilters.search).toBe('john doe')
      })

      test('resets debounce timer on rapid typing', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('search', 'j')
        })

        act(() => {
          jest.advanceTimersByTime(200)
        })

        act(() => {
          result.current.setFilter('search', 'jo')
        })

        act(() => {
          jest.advanceTimersByTime(200)
        })

        act(() => {
          result.current.setFilter('search', 'john')
        })

        // Still waiting for debounce
        expect(result.current.debouncedFilters.search).toBe('')

        act(() => {
          jest.advanceTimersByTime(500)
        })

        // Now debounced with final value
        expect(result.current.debouncedFilters.search).toBe('john')
      })

      test('excludes empty search from API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.search).toBeUndefined()
      })

      test('includes non-empty search in API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('search', 'test')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.search).toBe('test')
      })
    })

    describe('Group Filter', () => {
      test('initializes group filter with "all" value', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        expect(result.current.filters.group_id).toBe('all')
      })

      test('updates group filter value', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('group_id', '5')
        })

        expect(result.current.filters.group_id).toBe('5')
      })

      test('excludes "all" group from API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.group_id).toBeUndefined()
      })

      test('includes specific group in API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('group_id', '3')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.group_id).toBe('3')
      })
    })

    describe('Status Filter', () => {
      test('initializes status filter with "all" value', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        expect(result.current.filters.status).toBe('all')
      })

      test('updates status filter to active', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('status', 'active')
        })

        expect(result.current.filters.status).toBe('active')
      })

      test('updates status filter to inactive', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('status', 'inactive')
        })

        expect(result.current.filters.status).toBe('inactive')
      })

      test('excludes "all" status from API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.status).toBeUndefined()
      })

      test('includes specific status in API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('status', 'active')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.status).toBe('active')
      })
    })

    describe('Country Code Filter', () => {
      test('initializes country_code filter with "all" value', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        expect(result.current.filters.country_code).toBe('all')
      })

      test('updates country_code filter', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('country_code', 'US')
        })

        expect(result.current.filters.country_code).toBe('US')
      })

      test('excludes "all" country_code from API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.country_code).toBeUndefined()
      })

      test('includes specific country_code in API params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('country_code', 'GB')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.country_code).toBe('GB')
      })
    })

    describe('Combined Filters', () => {
      test('applies multiple filters together', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            search: 'john',
            group_id: '2',
            status: 'active',
            country_code: 'US',
          })
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params).toEqual({
          search: 'john',
          group_id: '2',
          status: 'active',
          country_code: 'US',
        })
      })

      test('excludes default values from combined params', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            search: 'test',
            group_id: 'all',
            status: 'active',
            country_code: 'all',
          })
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params).toEqual({
          search: 'test',
          status: 'active',
        })
        expect(params.group_id).toBeUndefined()
        expect(params.country_code).toBeUndefined()
      })

      test('hasActiveFilters returns true when any filter is active', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        expect(result.current.hasActiveFilters).toBe(false)

        act(() => {
          result.current.setFilter('status', 'active')
        })

        expect(result.current.hasActiveFilters).toBe(true)
      })

      test('hasActiveFilters returns false when all filters are at default', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('status', 'active')
        })

        expect(result.current.hasActiveFilters).toBe(true)

        act(() => {
          result.current.setFilter('status', 'all')
        })

        expect(result.current.hasActiveFilters).toBe(false)
      })
    })

    describe('Reset Filters', () => {
      test('resets all filters to initial values', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            search: 'test',
            group_id: '5',
            status: 'inactive',
            country_code: 'CA',
          })
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        expect(result.current.filters.search).toBe('test')

        act(() => {
          result.current.resetFilters()
        })

        expect(result.current.filters).toEqual(subscriberFilters)
        expect(result.current.debouncedFilters).toEqual(subscriberFilters)
      })

      test('resets single filter to initial value', () => {
        const { result } = renderHook(() => useFilters(subscriberFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            search: 'test',
            status: 'active',
          })
        })

        act(() => {
          result.current.resetFilter('search')
        })

        expect(result.current.filters.search).toBe('')
        expect(result.current.filters.status).toBe('active')
      })
    })
  })

  describe('Outbox Page Filters', () => {
    const outboxFilters = { search: '', status: 'all', date_from: '', date_to: '' }

    describe('Search Filter', () => {
      test('initializes search filter with empty string', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        expect(result.current.filters.search).toBe('')
      })

      test('debounces search value', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('search', '+1234567890')
        })

        expect(result.current.debouncedFilters.search).toBe('')

        act(() => {
          jest.advanceTimersByTime(500)
        })

        expect(result.current.debouncedFilters.search).toBe('+1234567890')
      })
    })

    describe('Status Filter', () => {
      test('initializes status filter with "all"', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        expect(result.current.filters.status).toBe('all')
      })

      test('filters by success status', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('status', 'success')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.status).toBe('success')
      })

      test('filters by failed status', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('status', 'failed')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.status).toBe('failed')
      })
    })

    describe('Date Range Filter', () => {
      test('initializes date filters with empty strings', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        expect(result.current.filters.date_from).toBe('')
        expect(result.current.filters.date_to).toBe('')
      })

      test('sets date_from filter', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('date_from', '2024-01-01')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.date_from).toBe('2024-01-01')
      })

      test('sets date_to filter', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilter('date_to', '2024-12-31')
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.date_to).toBe('2024-12-31')
      })

      test('sets both date filters for range', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            date_from: '2024-01-01',
            date_to: '2024-01-31',
          })
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.date_from).toBe('2024-01-01')
        expect(params.date_to).toBe('2024-01-31')
      })

      test('excludes empty date filters from API params', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params.date_from).toBeUndefined()
        expect(params.date_to).toBeUndefined()
      })
    })

    describe('Combined Filters', () => {
      test('applies all outbox filters together', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            search: '+1234',
            status: 'failed',
            date_from: '2024-01-01',
            date_to: '2024-01-31',
          })
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        const params = result.current.getApiParams()
        expect(params).toEqual({
          search: '+1234',
          status: 'failed',
          date_from: '2024-01-01',
          date_to: '2024-01-31',
        })
      })

      test('hasActiveFilters detects active date filters', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        expect(result.current.hasActiveFilters).toBe(false)

        act(() => {
          result.current.setFilter('date_from', '2024-01-01')
        })

        expect(result.current.hasActiveFilters).toBe(true)
      })

      test('hasActiveFilters returns false after clearing date filters', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            date_from: '2024-01-01',
            date_to: '2024-01-31',
          })
        })

        expect(result.current.hasActiveFilters).toBe(true)

        act(() => {
          result.current.setFilters({
            date_from: '',
            date_to: '',
          })
        })

        expect(result.current.hasActiveFilters).toBe(false)
      })
    })

    describe('Reset Filters', () => {
      test('resets all outbox filters to initial values', () => {
        const { result } = renderHook(() => useFilters(outboxFilters, { debounceMs: 500 }))

        act(() => {
          result.current.setFilters({
            search: 'test',
            status: 'failed',
            date_from: '2024-01-01',
            date_to: '2024-12-31',
          })
        })

        act(() => {
          jest.advanceTimersByTime(500)
        })

        act(() => {
          result.current.resetFilters()
        })

        expect(result.current.filters).toEqual(outboxFilters)
        expect(result.current.debouncedFilters).toEqual(outboxFilters)
      })
    })
  })

  describe('onChange Callback', () => {
    test('calls onChange when filters change after debounce', () => {
      const onChange = jest.fn()
      const { result } = renderHook(() =>
        useFilters(
          { search: '', status: 'all' },
          { debounceMs: 500, onChange }
        )
      )

      // Initial debounce
      act(() => {
        jest.advanceTimersByTime(500)
      })

      onChange.mockClear()

      act(() => {
        result.current.setFilter('search', 'test')
      })

      expect(onChange).not.toHaveBeenCalled()

      act(() => {
        jest.advanceTimersByTime(500)
      })

      expect(onChange).toHaveBeenCalledWith({ search: 'test', status: 'all' })
    })

    test('calls onChange with all updated filters', () => {
      const onChange = jest.fn()
      const { result } = renderHook(() =>
        useFilters(
          { search: '', status: 'all', group_id: 'all' },
          { debounceMs: 500, onChange }
        )
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      onChange.mockClear()

      act(() => {
        result.current.setFilters({
          search: 'john',
          status: 'active',
        })
      })

      act(() => {
        jest.advanceTimersByTime(500)
      })

      expect(onChange).toHaveBeenCalledWith({
        search: 'john',
        status: 'active',
        group_id: 'all',
      })
    })
  })

  describe('Search Helper', () => {
    test('provides search shortcut getter', () => {
      const { result } = renderHook(() =>
        useFilters({ search: 'initial value', status: 'all' })
      )

      expect(result.current.search).toBe('initial value')
    })

    test('provides setSearch shortcut setter', () => {
      const { result } = renderHook(() =>
        useFilters({ search: '', status: 'all' })
      )

      act(() => {
        result.current.setSearch('new value')
      })

      expect(result.current.search).toBe('new value')
      expect(result.current.filters.search).toBe('new value')
    })
  })

  describe('Edge Cases', () => {
    test('handles null filter values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: null, status: 'all' })
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      const params = result.current.getApiParams()
      expect(params.search).toBeUndefined()
    })

    test('handles undefined filter values', () => {
      const { result } = renderHook(() =>
        useFilters({ search: undefined, status: 'all' })
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      const params = result.current.getApiParams()
      expect(params.search).toBeUndefined()
    })

    test('treats "all" as default for dynamically added filters', () => {
      const { result } = renderHook(() => useFilters({}))

      act(() => {
        result.current.setFilter('newFilter', 'all')
      })

      expect(result.current.hasActiveFilters).toBe(false)
    })

    test('handles zero as a valid filter value', () => {
      const { result } = renderHook(() =>
        useFilters({ page: 0, status: 'all' })
      )

      act(() => {
        jest.advanceTimersByTime(500)
      })

      const params = result.current.getApiParams()
      // Zero should be included as it's a valid value
      expect(params.page).toBe(0)
    })

    test('preserves filter state across multiple setFilter calls', () => {
      const { result } = renderHook(() =>
        useFilters({ a: '', b: '', c: '' })
      )

      act(() => {
        result.current.setFilter('a', '1')
      })

      act(() => {
        result.current.setFilter('b', '2')
      })

      act(() => {
        result.current.setFilter('c', '3')
      })

      expect(result.current.filters).toEqual({ a: '1', b: '2', c: '3' })
    })
  })
})
