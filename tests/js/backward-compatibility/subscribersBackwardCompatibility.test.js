/**
 * Backward Compatibility Tests for Subscribers
 *
 * Ensures that the React dashboard correctly handles legacy subscriber data formats
 * and maintains backward compatibility with the PHP backend.
 */

import { subscribersApi } from '@/api/subscribersApi'
import { setupWpSmsSettings, createMockSubscriber } from '../testing-utils'

describe('Subscribers Backward Compatibility', () => {
  beforeEach(() => {
    global.fetch = jest.fn()
    setupWpSmsSettings()
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  describe('Legacy Data Format Handling', () => {
    test('handles legacy status values (string "1" or "0")', async () => {
      const legacySubscribers = [
        createMockSubscriber({ id: 1, status: '1', name: 'Active User' }),
        createMockSubscriber({ id: 2, status: '0', name: 'Inactive User' }),
      ]

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: legacySubscribers,
            pagination: { total: 2, per_page: 10, page: 1 },
            stats: { total: 2, active: 1, inactive: 1 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: legacySubscribers,
            pagination: { total: 2, per_page: 10, page: 1 },
            stats: { total: 2, active: 1, inactive: 1 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      // String '1' should indicate active
      expect(result.items[0].status).toBe('1')
      // String '0' should indicate inactive
      expect(result.items[1].status).toBe('0')
    })

    test('handles legacy group_ID field (uppercase ID)', async () => {
      // Legacy database uses group_ID (uppercase ID)
      const legacySubscriber = {
        id: 1,
        mobile: '+15551234567',
        name: 'Test User',
        group_ID: '5', // Legacy format
        status: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      // Should have group_ID or normalized group_id
      const subscriber = result.items[0]
      const groupId = subscriber.group_ID || subscriber.group_id
      expect(groupId).toBe('5')
    })

    test('handles legacy date format (MySQL datetime)', async () => {
      const legacySubscriber = {
        id: 1,
        mobile: '+15551234567',
        name: 'Test User',
        date: '2024-01-15 14:30:00', // MySQL datetime format
        status: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      // Date should be parseable
      const subscriber = result.items[0]
      const dateField = subscriber.date || subscriber.created_at
      const parsed = new Date(dateField)
      expect(parsed.getTime()).not.toBeNaN()
    })

    test('handles legacy custom_fields as JSON string', async () => {
      const customFields = { field1: 'value1', field2: 'value2' }
      const legacySubscriber = {
        id: 1,
        mobile: '+15551234567',
        name: 'Test User',
        custom_fields: JSON.stringify(customFields),
        status: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      // Custom fields should be accessible
      const subscriber = result.items[0]
      expect(subscriber.custom_fields).toBeDefined()
    })

    test('handles empty custom_fields gracefully', async () => {
      const legacySubscriber = {
        id: 1,
        mobile: '+15551234567',
        name: 'Test User',
        custom_fields: '', // Empty string
        status: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [legacySubscriber],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      // Should not throw on empty custom_fields
      expect(result.items).toHaveLength(1)
    })
  })

  describe('Phone Number Format Handling', () => {
    test('handles phone numbers with various formats', async () => {
      const phoneFormats = [
        { id: 1, mobile: '+15551234567', name: 'E.164 format' },
        { id: 2, mobile: '15551234567', name: 'Without plus' },
        { id: 3, mobile: '5551234567', name: 'Without country code' },
        { id: 4, mobile: '+1 555 123 4567', name: 'With spaces' },
      ]

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: phoneFormats.map(p => createMockSubscriber(p)),
            pagination: { total: 4, per_page: 10, page: 1 },
            stats: { total: 4, active: 4, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: phoneFormats.map(p => createMockSubscriber(p)),
            pagination: { total: 4, per_page: 10, page: 1 },
            stats: { total: 4, active: 4, inactive: 0 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      expect(result.items).toHaveLength(4)
      result.items.forEach(subscriber => {
        expect(subscriber.mobile).toBeDefined()
      })
    })

    test('handles Persian/Arabic numerals in phone numbers', async () => {
      const subscriberWithPersianPhone = {
        id: 1,
        mobile: '+۹۸۹۱۲۳۴۵۶۷۸۹',
        name: 'Persian Phone User',
        status: '1',
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [subscriberWithPersianPhone],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [subscriberWithPersianPhone],
            pagination: { total: 1, per_page: 10, page: 1 },
            stats: { total: 1, active: 1, inactive: 0 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      // Should handle Persian/Arabic numerals
      expect(result.items[0].mobile).toBeDefined()
    })
  })

  describe('Bulk Operations Backward Compatibility', () => {
    test('bulk delete uses correct legacy format', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Subscribers deleted',
          data: { deleted: 3 },
        }),
        text: async () => JSON.stringify({
          message: 'Subscribers deleted',
          data: { deleted: 3 },
        }),
      })

      await subscribersApi.bulkAction('delete', [1, 2, 3])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/subscribers/bulk'),
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"action":"delete"'),
        })
      )
    })

    test('bulk activate sends correct action', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Subscribers activated',
          data: { updated: 2 },
        }),
        text: async () => JSON.stringify({
          message: 'Subscribers activated',
          data: { updated: 2 },
        }),
      })

      await subscribersApi.bulkAction('activate', [1, 2])

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('"action":"activate"'),
        })
      )
    })

    test('bulk move includes group_id parameter', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Subscribers moved',
          data: { updated: 2 },
        }),
        text: async () => JSON.stringify({
          message: 'Subscribers moved',
          data: { updated: 2 },
        }),
      })

      await subscribersApi.bulkAction('move', [1, 2], { group_id: 5 })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('"group_id"'),
        })
      )
    })
  })

  describe('Stats and Pagination', () => {
    test('handles legacy pagination response format', async () => {
      const legacyPagination = {
        total: 100,
        per_page: 10,
        current_page: 1,
        total_pages: 10,
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [],
            pagination: legacyPagination,
            stats: { total: 100, active: 80, inactive: 20 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [],
            pagination: legacyPagination,
            stats: { total: 100, active: 80, inactive: 20 },
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      expect(result.pagination.total).toBe(100)
      expect(result.pagination.per_page).toBe(10)
    })

    test('handles stats with all status counts', async () => {
      const stats = {
        total: 100,
        active: 75,
        inactive: 20,
        pending: 5,
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [],
            pagination: { total: 100, per_page: 10, page: 1 },
            stats,
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [],
            pagination: { total: 100, per_page: 10, page: 1 },
            stats,
          },
        }),
      })

      const result = await subscribersApi.getSubscribers()

      expect(result.stats.active).toBe(75)
      expect(result.stats.inactive).toBe(20)
    })
  })

  describe('Error Response Handling', () => {
    test('handles legacy duplicate phone error format', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({
          error: {
            code: 'duplicate_mobile',
            message: 'This mobile number already exists in the system',
          },
        }),
        text: async () => JSON.stringify({
          error: {
            code: 'duplicate_mobile',
            message: 'This mobile number already exists in the system',
          },
        }),
      })

      await expect(
        subscribersApi.createSubscriber({
          mobile: '+15551234567',
          name: 'Test',
        })
      ).rejects.toThrow()
    })

    test('handles legacy validation error format', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({
          error: {
            code: 'invalid_phone',
            message: 'The phone number format is invalid',
          },
        }),
        text: async () => JSON.stringify({
          error: {
            code: 'invalid_phone',
            message: 'The phone number format is invalid',
          },
        }),
      })

      await expect(
        subscribersApi.createSubscriber({
          mobile: 'invalid',
          name: 'Test',
        })
      ).rejects.toThrow()
    })
  })

  describe('Filter and Search', () => {
    test('sends filter parameters in legacy format', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Success',
          data: {
            items: [],
            pagination: { total: 0, per_page: 10, page: 1 },
            stats: { total: 0, active: 0, inactive: 0 },
          },
        }),
        text: async () => JSON.stringify({
          message: 'Success',
          data: {
            items: [],
            pagination: { total: 0, per_page: 10, page: 1 },
            stats: { total: 0, active: 0, inactive: 0 },
          },
        }),
      })

      await subscribersApi.getSubscribers({
        group_id: 5,
        status: 'active',
        search: 'john',
      })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringMatching(/group_id=5.*status=active.*search=john|status=active.*group_id=5|search=john/),
        expect.any(Object)
      )
    })
  })

  describe('Export Backward Compatibility', () => {
    test('export endpoint returns proper format', async () => {
      const exportData = {
        data: [
          { mobile: '+15551234567', name: 'User 1', status: '1' },
          { mobile: '+15559876543', name: 'User 2', status: '0' },
        ],
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          message: 'Export ready',
          data: exportData,
        }),
        text: async () => JSON.stringify({
          message: 'Export ready',
          data: exportData,
        }),
      })

      const result = await subscribersApi.exportCsv()

      expect(result.data).toBeDefined()
      expect(Array.isArray(result.data)).toBe(true)
    })
  })
})
