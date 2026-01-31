import { scheduledApi } from '@/api/scheduledApi'

describe('scheduledApi', () => {
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
    test('fetches scheduled messages with default params', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [
            { id: 1, date: '2025-01-15 10:00:00', sender: 'Test', recipient: '+123', message: 'Hello', status: 'pending' },
          ],
          pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
          stats: { total: 1, pending: 1, sent: 0, failed: 0 },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await scheduledApi.getMessages({ page: 1, per_page: 20 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('scheduled'),
        expect.objectContaining({
          method: 'GET',
          headers: expect.objectContaining({
            'X-WP-Nonce': 'test-nonce',
          }),
        })
      )
      expect(result.items).toHaveLength(1)
      expect(result.items[0].status).toBe('pending')
      expect(result.pagination.total).toBe(1)
      expect(result.stats.pending).toBe(1)
    })

    test('returns empty defaults on missing data', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Success', data: {} }),
        text: async () => JSON.stringify({ message: 'Success', data: {} }),
      })

      const result = await scheduledApi.getMessages()

      expect(result.items).toEqual([])
      expect(result.pagination.total).toBe(0)
      expect(result.stats.total).toBe(0)
    })

    test('passes filter parameters', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Success', data: { items: [], pagination: {}, stats: {} } }),
        text: async () => JSON.stringify({ message: 'Success', data: { items: [], pagination: {}, stats: {} } }),
      })

      await scheduledApi.getMessages({ status: 'pending', search: 'hello', date_from: '2025-01-01' })

      const calledUrl = global.fetch.mock.calls[0][0]
      expect(calledUrl).toContain('status=pending')
      expect(calledUrl).toContain('search=hello')
      expect(calledUrl).toContain('date_from=2025-01-01')
    })
  })

  describe('deleteMessage', () => {
    test('deletes a scheduled message by ID', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Deleted' }),
        text: async () => JSON.stringify({ message: 'Deleted' }),
      })

      const result = await scheduledApi.deleteMessage(5)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('scheduled/5'),
        expect.objectContaining({ method: 'DELETE' })
      )
      expect(result.success).toBe(true)
    })
  })

  describe('sendNow', () => {
    test('sends a scheduled message immediately', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Sent' }),
        text: async () => JSON.stringify({ message: 'Sent' }),
      })

      const result = await scheduledApi.sendNow(3)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('scheduled/3/send'),
        expect.objectContaining({ method: 'POST' })
      )
      expect(result.success).toBe(true)
    })
  })

  describe('bulkAction', () => {
    test('performs bulk delete action', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Done', data: { affected: 3 } }),
        text: async () => JSON.stringify({ message: 'Done', data: { affected: 3 } }),
      })

      const result = await scheduledApi.bulkAction('delete', [1, 2, 3])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('scheduled/bulk'),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ action: 'delete', ids: [1, 2, 3] }),
        })
      )
      expect(result.affected).toBe(3)
    })

    test('performs bulk send action', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Done', data: { affected: 2 } }),
        text: async () => JSON.stringify({ message: 'Done', data: { affected: 2 } }),
      })

      const result = await scheduledApi.bulkAction('send', [1, 2])

      expect(result.affected).toBe(2)
    })
  })

  describe('exportCsv', () => {
    test('exports scheduled messages with filters', async () => {
      const mockCsvData = [
        ['ID', 'Scheduled Date', 'Sender', 'Recipient', 'Message', 'Status'],
        [1, '2025-01-15 10:00:00', 'Test', '+123', 'Hello', 'Pending'],
      ]

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Export ready', data: { data: mockCsvData, filename: 'export.csv', count: 1 } }),
        text: async () => JSON.stringify({ message: 'Export ready', data: { data: mockCsvData, filename: 'export.csv', count: 1 } }),
      })

      const result = await scheduledApi.exportCsv({ status: 'pending' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('scheduled/export'),
        expect.objectContaining({ method: 'GET' })
      )
      expect(result.data).toHaveLength(2) // header + 1 data row
      expect(result.data[0]).toEqual(expect.arrayContaining(['ID', 'Scheduled Date']))
      expect(result.filename).toBe('export.csv')
      expect(result.count).toBe(1)
    })

    test('returns empty defaults when no data', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Export ready', data: {} }),
        text: async () => JSON.stringify({ message: 'Export ready', data: {} }),
      })

      const result = await scheduledApi.exportCsv()

      expect(result.data).toEqual([])
      expect(result.count).toBe(0)
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

      await expect(scheduledApi.getMessages()).rejects.toThrow()
    })

    test('throws error on network failure', async () => {
      global.fetch.mockRejectedValueOnce(new Error('Network error'))

      await expect(scheduledApi.getMessages()).rejects.toThrow('Network error')
    })
  })
})
