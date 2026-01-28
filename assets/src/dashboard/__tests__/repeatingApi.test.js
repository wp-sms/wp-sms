import { repeatingApi } from '../api/repeatingApi'

describe('repeatingApi', () => {
  beforeEach(() => {
    global.fetch = jest.fn()
    global.window.wpSmsSettings = {
      apiUrl: 'http://localhost/wp-json/wpsms/v1/',
      nonce: 'test-nonce',
      settings: {},
    }
  })

  afterEach(() => {
    jest.restoreAllMocks()
  })

  describe('getMessages', () => {
    test('fetches repeating messages with default params', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [
            {
              id: 1,
              sender: 'Test',
              recipient: '+123',
              message: 'Hello',
              interval: 5,
              interval_unit: 'minutes',
              status: 'active',
              next_occurrence: '2025-01-20 10:00:00',
            },
          ],
          pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
          stats: { total: 1, active: 1, ended: 0 },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await repeatingApi.getMessages({ page: 1, per_page: 20 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('repeating'),
        expect.objectContaining({
          method: 'GET',
          headers: expect.objectContaining({
            'X-WP-Nonce': 'test-nonce',
          }),
        })
      )
      expect(result.items).toHaveLength(1)
      expect(result.items[0].status).toBe('active')
      expect(result.stats.active).toBe(1)
    })

    test('returns empty defaults on missing data', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Success', data: {} }),
        text: async () => JSON.stringify({ message: 'Success', data: {} }),
      })

      const result = await repeatingApi.getMessages()

      expect(result.items).toEqual([])
      expect(result.pagination.total).toBe(0)
      expect(result.stats.total).toBe(0)
    })
  })

  describe('deleteMessage', () => {
    test('deletes a repeating message by ID', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Deleted' }),
        text: async () => JSON.stringify({ message: 'Deleted' }),
      })

      const result = await repeatingApi.deleteMessage(7)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('repeating/7'),
        expect.objectContaining({ method: 'DELETE' })
      )
      expect(result.success).toBe(true)
    })
  })

  describe('bulkAction', () => {
    test('performs bulk delete action', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Done', data: { affected: 2 } }),
        text: async () => JSON.stringify({ message: 'Done', data: { affected: 2 } }),
      })

      const result = await repeatingApi.bulkAction('delete', [1, 2])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('repeating/bulk'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ action: 'delete', ids: [1, 2] }),
        })
      )
      expect(result.affected).toBe(2)
    })
  })

  describe('exportCsv', () => {
    test('exports repeating messages with status filter', async () => {
      const mockCsvData = [
        ['ID', 'Sender', 'Recipient', 'Message', 'Interval', 'Starts At', 'Ends At', 'Status'],
        [1, 'Test', '+123', 'Hello', 'Every 5 minutes', '2025-01-15 10:00:00', '', 'Active'],
      ]

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Export ready', data: { data: mockCsvData, filename: 'export.csv', count: 1 } }),
        text: async () => JSON.stringify({ message: 'Export ready', data: { data: mockCsvData, filename: 'export.csv', count: 1 } }),
      })

      const result = await repeatingApi.exportCsv({ status: 'active' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('repeating/export'),
        expect.objectContaining({ method: 'GET' })
      )
      expect(result.data).toHaveLength(2) // header + 1 data row
      expect(result.data[0]).toEqual(expect.arrayContaining(['ID', 'Sender']))
      expect(result.count).toBe(1)
    })
  })

  describe('formatInterval', () => {
    test('formats singular intervals', () => {
      expect(repeatingApi.formatInterval(1, 'minute')).toBe('Every 1 minute')
      expect(repeatingApi.formatInterval(1, 'hour')).toBe('Every 1 hour')
      expect(repeatingApi.formatInterval(1, 'day')).toBe('Every 1 day')
    })

    test('formats plural intervals', () => {
      expect(repeatingApi.formatInterval(5, 'minute')).toBe('Every 5 minutes')
      expect(repeatingApi.formatInterval(3, 'hour')).toBe('Every 3 hours')
      expect(repeatingApi.formatInterval(2, 'week')).toBe('Every 2 weeks')
    })

    test('handles unknown units gracefully', () => {
      expect(repeatingApi.formatInterval(1, 'unknown')).toBe('Every 1 unknown')
    })
  })

  describe('getIntervalUnitOptions', () => {
    test('returns all interval unit options', () => {
      const options = repeatingApi.getIntervalUnitOptions()

      expect(options).toHaveLength(5)
      expect(options.map(o => o.value)).toEqual(['minute', 'hour', 'day', 'week', 'month'])
    })
  })

  describe('error handling', () => {
    test('throws error on API failure', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({ error: { message: 'Server error' } }),
        text: async () => JSON.stringify({ error: { message: 'Server error' } }),
      })

      await expect(repeatingApi.getMessages()).rejects.toThrow()
    })
  })
})
