import { outboxApi } from '@/api/outboxApi'
import { downloadCsv } from '@/lib/utils'

describe('outboxApi', () => {
  beforeEach(() => {
    global.fetch = jest.fn()
    global.window.wpSmsSettings = {
      apiUrl: 'http://localhost/wp-json/wpsms/v1/',
      nonce: 'test-nonce',
      settings: {},
      proSettings: {},
    }
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  describe('getMessages', () => {
    test('fetches outbox messages with pagination', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [
            { id: 1, recipient: '+1234567890', message: 'Test 1', status: 'success' },
            { id: 2, recipient: '+0987654321', message: 'Test 2', status: 'failed' },
          ],
          pagination: {
            total: 50,
            total_pages: 3,
            current_page: 1,
            per_page: 20,
          },
          stats: {
            total: 50,
            success: 45,
            failed: 5,
          },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.getMessages({ page: 1, per_page: 20 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('outbox'),
        expect.any(Object)
      )

      expect(result.items).toHaveLength(2)
      expect(result.pagination.total).toBe(50)
      expect(result.stats.success).toBe(45)
    })

    test('filters by status', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [{ id: 2, status: 'failed' }],
          pagination: { total: 5, total_pages: 1, current_page: 1, per_page: 20 },
          stats: { total: 5, success: 0, failed: 5 },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.getMessages({ status: 'failed' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('status=failed'),
        expect.any(Object)
      )

      expect(result.items[0].status).toBe('failed')
    })

    test('returns empty data on error', async () => {
      const mockResponse = { message: 'Success', data: null }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.getMessages()

      expect(result.items).toEqual([])
      expect(result.pagination.total).toBe(0)
      expect(result.stats.total).toBe(0)
    })
  })

  describe('getMessage', () => {
    test('fetches single message details', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          id: 1,
          recipient: '+1234567890',
          message: 'Test message',
          status: 'success',
          date: '2024-01-15 10:30:00',
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.getMessage(1)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('outbox/1'),
        expect.any(Object)
      )

      expect(result.id).toBe(1)
      expect(result.message).toBe('Test message')
    })
  })

  describe('deleteMessage', () => {
    test('deletes a message successfully', async () => {
      const mockResponse = { message: 'Message deleted' }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.deleteMessage(1)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('outbox/1'),
        expect.objectContaining({ method: 'DELETE' })
      )

      expect(result.success).toBe(true)
    })
  })

  describe('resendMessage', () => {
    test('resends a message successfully', async () => {
      const mockResponse = {
        message: 'Message resent',
        data: { credit: 98 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.resendMessage(1)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('outbox/1/resend'),
        expect.objectContaining({ method: 'POST' })
      )

      expect(result.success).toBe(true)
      expect(result.credit).toBe(98)
    })
  })

  describe('bulkAction', () => {
    test('performs bulk delete', async () => {
      const mockResponse = {
        message: 'Bulk action completed',
        data: {
          affected: 5,
          errors: [],
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.bulkAction('delete', [1, 2, 3, 4, 5])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('outbox/bulk'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"action":"delete"'),
        })
      )

      expect(result.success).toBe(true)
      expect(result.affected).toBe(5)
    })

    test('handles partial failures in bulk action', async () => {
      const mockResponse = {
        message: 'Bulk action completed with errors',
        data: {
          affected: 3,
          errors: [
            { id: 4, message: 'Message not found' },
            { id: 5, message: 'Already deleted' },
          ],
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.bulkAction('delete', [1, 2, 3, 4, 5])

      expect(result.affected).toBe(3)
      expect(result.errors).toHaveLength(2)
    })
  })

  describe('exportCsv', () => {
    test('exports messages to CSV format', async () => {
      const mockResponse = {
        message: 'Export ready',
        data: {
          data: [
            ['ID', 'Recipient', 'Message', 'Status', 'Date'],
            ['1', '+1234567890', 'Test', 'success', '2024-01-15'],
          ],
          filename: 'outbox-export-2024-01-15.csv',
          count: 1,
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.exportCsv({ status: 'success' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('outbox/export'),
        expect.any(Object)
      )

      expect(result.data).toHaveLength(2)
      expect(result.filename).toContain('outbox-export')
      expect(result.count).toBe(1)
    })

    test('generates default filename if not provided', async () => {
      const mockResponse = {
        message: 'Export ready',
        data: { data: [], count: 0 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await outboxApi.exportCsv()

      expect(result.filename).toContain('outbox-export')
      expect(result.filename).toContain('.csv')
    })
  })

  describe('downloadCsv (utility function)', () => {
    test('creates and triggers download', () => {
      // Mock DOM methods
      const mockLink = {
        href: '',
        download: '',
        click: jest.fn(),
      }
      document.createElement = jest.fn().mockReturnValue(mockLink)
      document.body.appendChild = jest.fn()
      document.body.removeChild = jest.fn()
      URL.createObjectURL = jest.fn().mockReturnValue('blob:test')
      URL.revokeObjectURL = jest.fn()

      const data = [
        ['ID', 'Recipient', 'Message'],
        ['1', '+1234567890', 'Test'],
      ]

      downloadCsv(data, 'test-export.csv')

      expect(document.createElement).toHaveBeenCalledWith('a')
      expect(mockLink.download).toBe('test-export.csv')
      expect(mockLink.click).toHaveBeenCalled()
      expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:test')
    })
  })
})
