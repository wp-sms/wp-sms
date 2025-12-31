import { smsApi } from '../api/smsApi'

describe('smsApi', () => {
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

  describe('send', () => {
    test('sends SMS message successfully', async () => {
      const mockResponse = {
        message: 'Message sent successfully',
        data: {
          recipient_count: 5,
          credit: 95,
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await smsApi.send({
        message: 'Test message',
        recipients: {
          groups: ['1'],
          roles: [],
          numbers: ['+1234567890'],
        },
        flash: false,
      })

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/send/quick',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('Test message'),
        })
      )

      expect(result.success).toBe(true)
      expect(result.recipientCount).toBe(5)
      expect(result.credit).toBe(95)
    })

    test('sends flash SMS when specified', async () => {
      const mockResponse = {
        message: 'Message sent',
        data: { recipient_count: 1, credit: 99 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      await smsApi.send({
        message: 'Flash message',
        recipients: { groups: [], roles: [], numbers: ['+1234567890'] },
        flash: true,
      })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('"flash":true'),
        })
      )
    })

    test('includes media URL for MMS', async () => {
      const mockResponse = {
        message: 'MMS sent',
        data: { recipient_count: 1, credit: 98 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      await smsApi.send({
        message: 'MMS message',
        recipients: { groups: [], roles: [], numbers: ['+1234567890'] },
        mediaUrl: 'https://example.com/image.jpg',
      })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('https://example.com/image.jpg'),
        })
      )
    })

    test('handles send failure', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { message: 'Insufficient credit' } }),
        text: async () => JSON.stringify({ error: { message: 'Insufficient credit' } }),
      })

      await expect(
        smsApi.send({
          message: 'Test',
          recipients: { groups: [], roles: [], numbers: ['+1234567890'] },
        })
      ).rejects.toThrow('Insufficient credit')
    })
  })

  describe('getRecipientCount', () => {
    test('returns recipient count for groups and numbers', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          total: 25,
          groups: 20,
          roles: 0,
          numbers: 5,
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await smsApi.getRecipientCount({
        groups: ['1', '2'],
        roles: [],
        numbers: ['+1234567890', '+0987654321'],
      })

      expect(result.total).toBe(25)
      expect(result.groups).toBe(20)
      expect(result.numbers).toBe(5)
    })

    test('returns zeros for empty recipients', async () => {
      const mockResponse = {
        message: 'Success',
        data: { total: 0, groups: 0, roles: 0, numbers: 0 },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await smsApi.getRecipientCount({
        groups: [],
        roles: [],
        numbers: [],
      })

      expect(result.total).toBe(0)
    })
  })

  describe('getCredit', () => {
    test('returns credit balance', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          credit: 150,
          gateway: 'twilio',
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await smsApi.getCredit()

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/credit',
        expect.objectContaining({
          method: 'GET',
        })
      )

      expect(result.credit).toBe(150)
      expect(result.gateway).toBe('twilio')
    })
  })

  describe('validateNumbers', () => {
    test('validates phone numbers', async () => {
      const mockResponse = {
        message: 'Validation complete',
        data: {
          valid: ['+1234567890', '+0987654321'],
          invalid: ['invalid-number'],
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await smsApi.validateNumbers([
        '+1234567890',
        '+0987654321',
        'invalid-number',
      ])

      expect(result.valid).toHaveLength(2)
      expect(result.invalid).toHaveLength(1)
      expect(result.invalid[0]).toBe('invalid-number')
    })
  })
})
