import { subscribersApi } from '../api/subscribersApi'

describe('subscribersApi', () => {
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

  describe('getSubscribers', () => {
    test('fetches subscribers with pagination', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [
            { id: 1, name: 'John', mobile: '+1234567890', status: '1' },
            { id: 2, name: 'Jane', mobile: '+0987654321', status: '1' },
          ],
          pagination: {
            total: 100,
            total_pages: 5,
            current_page: 1,
            per_page: 20,
          },
          stats: {
            total: 100,
            active: 85,
            inactive: 15,
          },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.getSubscribers({ page: 1, per_page: 20 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('subscribers'),
        expect.any(Object)
      )

      expect(result.items).toHaveLength(2)
      expect(result.pagination.total).toBe(100)
      expect(result.stats.active).toBe(85)
    })

    test('filters by group', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [{ id: 1, name: 'John', group_id: 5 }],
          pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
          stats: { total: 1, active: 1, inactive: 0 },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      await subscribersApi.getSubscribers({ group_id: 5 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('group_id=5'),
        expect.any(Object)
      )
    })

    test('filters by status', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [],
          pagination: { total: 0, total_pages: 1, current_page: 1, per_page: 20 },
          stats: { total: 0, active: 0, inactive: 0 },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      await subscribersApi.getSubscribers({ status: 'active' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('status=active'),
        expect.any(Object)
      )
    })

    test('searches by keyword', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          items: [{ id: 1, name: 'John Doe', mobile: '+1234567890' }],
          pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
          stats: { total: 1, active: 1, inactive: 0 },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      await subscribersApi.getSubscribers({ search: 'John' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('search=John'),
        expect.any(Object)
      )
    })
  })

  describe('createSubscriber', () => {
    test('creates a new subscriber', async () => {
      const mockResponse = {
        message: 'Subscriber created',
        data: {
          id: 10,
          name: 'New User',
          mobile: '+1234567890',
          status: '1',
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.createSubscriber({
        name: 'New User',
        mobile: '+1234567890',
        group_id: 1,
        status: '1',
      })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('subscribers'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('New User'),
        })
      )

      expect(result.success).toBe(true)
      expect(result.subscriber.id).toBe(10)
      expect(result.subscriber.name).toBe('New User')
    })

    test('handles duplicate phone number error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { message: 'Phone number already exists' } }),
        text: async () => JSON.stringify({ error: { message: 'Phone number already exists' } }),
      })

      await expect(
        subscribersApi.createSubscriber({
          name: 'Duplicate',
          mobile: '+1234567890',
        })
      ).rejects.toThrow('Phone number already exists')
    })

    test('handles invalid phone number error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 400, message: 'Invalid Mobile Number.' } }),
        text: async () => JSON.stringify({ error: { code: 400, message: 'Invalid Mobile Number.' } }),
      })

      await expect(
        subscribersApi.createSubscriber({
          name: 'Test User',
          mobile: 'invalid-phone',
        })
      ).rejects.toThrow('Invalid Mobile Number.')
    })

    test('handles missing country code error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 400, message: "The mobile number doesn't contain the country code." } }),
        text: async () => JSON.stringify({ error: { code: 400, message: "The mobile number doesn't contain the country code." } }),
      })

      await expect(
        subscribersApi.createSubscriber({
          name: 'Test User',
          mobile: '9123456789', // No + prefix
        })
      ).rejects.toThrow("The mobile number doesn't contain the country code.")
    })

    test('handles invalid phone length error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 400, message: 'The mobile number length is invalid.' } }),
        text: async () => JSON.stringify({ error: { code: 400, message: 'The mobile number length is invalid.' } }),
      })

      await expect(
        subscribersApi.createSubscriber({
          name: 'Test User',
          mobile: '+123', // Too short
        })
      ).rejects.toThrow('The mobile number length is invalid.')
    })

    test('handles invalid country code error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 400, message: 'The mobile number is not valid for your country.' } }),
        text: async () => JSON.stringify({ error: { code: 400, message: 'The mobile number is not valid for your country.' } }),
      })

      await expect(
        subscribersApi.createSubscriber({
          name: 'Test User',
          mobile: '+999123456789', // Invalid country code
        })
      ).rejects.toThrow('The mobile number is not valid for your country.')
    })

    test('handles error with message at root level', async () => {
      // Some APIs might return error message at root level
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ message: 'Root level error message' }),
        text: async () => JSON.stringify({ message: 'Root level error message' }),
      })

      await expect(
        subscribersApi.createSubscriber({
          name: 'Test User',
          mobile: 'invalid',
        })
      ).rejects.toThrow('Root level error message')
    })

    test('handles network timeout error', async () => {
      const abortError = new Error('The operation was aborted')
      abortError.name = 'AbortError'

      // Mock fetch to always reject with abort error (covers all retry attempts)
      global.fetch.mockRejectedValue(abortError)

      await expect(
        subscribersApi.createSubscriber({
          name: 'Test User',
          mobile: '+1234567890',
        })
      ).rejects.toThrow('Request timed out')
    }, 15000) // Extended timeout to account for retries
  })

  describe('updateSubscriber', () => {
    test('updates an existing subscriber', async () => {
      const mockResponse = {
        message: 'Subscriber updated',
        data: {
          id: 1,
          name: 'Updated Name',
          mobile: '+1234567890',
          status: '1',
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.updateSubscriber(1, {
        name: 'Updated Name',
      })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('subscribers/1'),
        expect.objectContaining({
          method: 'PUT',
        })
      )

      expect(result.success).toBe(true)
      expect(result.subscriber.name).toBe('Updated Name')
    })

    test('handles invalid phone number on update', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 400, message: 'Invalid Mobile Number.' } }),
        text: async () => JSON.stringify({ error: { code: 400, message: 'Invalid Mobile Number.' } }),
      })

      await expect(
        subscribersApi.updateSubscriber(1, {
          mobile: 'invalid-phone',
        })
      ).rejects.toThrow('Invalid Mobile Number.')
    })

    test('handles subscriber not found on update', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 404,
        json: async () => ({ error: { code: 404, message: 'Subscriber not found' } }),
        text: async () => JSON.stringify({ error: { code: 404, message: 'Subscriber not found' } }),
      })

      await expect(
        subscribersApi.updateSubscriber(99999, {
          name: 'New Name',
        })
      ).rejects.toThrow('Subscriber not found')
    })
  })

  describe('deleteSubscriber', () => {
    test('deletes a subscriber', async () => {
      const mockResponse = { message: 'Subscriber deleted' }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.deleteSubscriber(1)

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('subscribers/1'),
        expect.objectContaining({ method: 'DELETE' })
      )

      expect(result.success).toBe(true)
    })
  })

  describe('bulkAction', () => {
    test('performs bulk delete', async () => {
      const mockResponse = {
        message: 'Bulk action completed',
        data: { affected: 5 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.bulkAction('delete', [1, 2, 3, 4, 5])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('subscribers/bulk'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"action":"delete"'),
        })
      )

      expect(result.affected).toBe(5)
    })

    test('performs bulk activate', async () => {
      const mockResponse = {
        message: 'Subscribers activated',
        data: { affected: 3 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.bulkAction('activate', [1, 2, 3])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('"action":"activate"'),
        })
      )

      expect(result.affected).toBe(3)
    })

    test('performs bulk move to group', async () => {
      const mockResponse = {
        message: 'Subscribers moved',
        data: { affected: 3 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.bulkAction('move_to_group', [1, 2, 3], { group_id: 5 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('"action":"move_to_group"'),
        })
      )

      expect(result.affected).toBe(3)
    })
  })

  describe('importCsv', () => {
    test('imports subscribers from CSV file', async () => {
      const mockResponse = {
        message: 'Import completed',
        data: {
          imported: 10,
          skipped: 2,
          errors: [],
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      // Create a mock File
      const mockFile = new File(['name,mobile\nJohn,+1234567890'], 'subscribers.csv', {
        type: 'text/csv',
      })

      const result = await subscribersApi.importCsv(mockFile, {
        group_id: 1,
        skip_duplicates: true,
      })

      expect(result.imported).toBe(10)
      expect(result.skipped).toBe(2)
    })

    test('handles import errors', async () => {
      const mockResponse = {
        message: 'Import completed with errors',
        data: {
          imported: 5,
          skipped: 0,
          errors: [
            { row: 3, message: 'Invalid phone number' },
            { row: 7, message: 'Missing required field' },
          ],
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const mockFile = new File(['test'], 'subscribers.csv', { type: 'text/csv' })
      const result = await subscribersApi.importCsv(mockFile)

      expect(result.imported).toBe(5)
      expect(result.errors).toHaveLength(2)
    })
  })

  describe('exportCsv', () => {
    test('exports subscribers to CSV', async () => {
      const mockResponse = {
        message: 'Export ready',
        data: {
          csv_data: [
            ['Name', 'Mobile', 'Group', 'Status'],
            ['John', '+1234567890', 'Group A', 'Active'],
          ],
          filename: 'subscribers-export.csv',
          count: 1,
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await subscribersApi.exportCsv({ group_id: 1 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('subscribers/export'),
        expect.any(Object)
      )

      expect(result.data).toHaveLength(2)
      expect(result.count).toBe(1)
    })
  })
})
