import { settingsApi } from '@/api/settingsApi'

describe('settingsApi', () => {
  beforeEach(() => {
    global.fetch = jest.fn()
    // Use the same nonce as jest.setup.js
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

  describe('getSettings', () => {
    test('fetches settings from API', async () => {
      const mockResponse = {
        message: 'Success',
        data: {
          settings: { gateway_name: 'twilio' },
          proSettings: {},
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await settingsApi.getSettings()

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/settings',
        expect.objectContaining({
          method: 'GET',
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'X-WP-Nonce': 'test-nonce',
          }),
        })
      )

      expect(result.settings.gateway_name).toBe('twilio')
    })

    test('throws error on API failure', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({ error: { message: 'Server error' } }),
        text: async () => JSON.stringify({ error: { message: 'Server error' } }),
      })

      await expect(settingsApi.getSettings()).rejects.toThrow('Server error')
    })
  })

  describe('updateSettings', () => {
    test('posts settings to API', async () => {
      const mockResponse = {
        message: 'Settings saved',
        data: {
          settings: { gateway_name: 'nexmo' },
          proSettings: {},
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const updateData = {
        settings: { gateway_name: 'nexmo' },
        proSettings: {},
      }

      const result = await settingsApi.updateSettings(updateData)

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/settings',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(updateData),
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'X-WP-Nonce': 'test-nonce',
          }),
        })
      )

      expect(result.settings.gateway_name).toBe('nexmo')
    })

    test('handles validation errors', async () => {
      const mockResponse = {
        message: 'Validation failed',
        data: {
          errors: {
            gateway_name: 'Invalid gateway selected',
          },
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      await expect(
        settingsApi.updateSettings({ settings: { gateway_name: 'invalid' } })
      ).rejects.toThrow()
    })
  })

  describe('testGateway', () => {
    test('tests gateway connection', async () => {
      const mockResponse = {
        message: 'Gateway connection successful',
        data: {
          credit: 100,
          gateway: 'twilio',
        },
      }

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse,
        text: async () => JSON.stringify(mockResponse),
      })

      const result = await settingsApi.testGateway()

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/settings/test-gateway',
        expect.objectContaining({
          method: 'POST',
        })
      )

      expect(result.success).toBe(true)
      expect(result.credit).toBe(100)
      expect(result.gateway).toBe('twilio')
    })

    test('handles gateway test failure', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({ error: { message: 'Invalid credentials' } }),
        text: async () => JSON.stringify({ error: { message: 'Invalid credentials' } }),
      })

      await expect(settingsApi.testGateway()).rejects.toThrow('Invalid credentials')
    })
  })

  describe('error handling', () => {
    test('provides user-friendly message on timeout', async () => {
      // Mock AbortController to simulate timeout
      const abortError = new Error('The operation was aborted')
      abortError.name = 'AbortError'

      // Mock fetch to reject with abort error (no retries for abort)
      global.fetch.mockRejectedValueOnce(abortError)
      global.fetch.mockRejectedValueOnce(abortError)
      global.fetch.mockRejectedValueOnce(abortError)
      global.fetch.mockRejectedValueOnce(abortError)

      await expect(settingsApi.getSettings()).rejects.toThrow(
        'Request timed out. Please check your connection and try again.'
      )
    }, 20000)

    test('handles empty response gracefully', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => null,
        text: async () => '',
      })

      await expect(settingsApi.getSettings()).rejects.toThrow('Empty response from server')
    })
  })
})
