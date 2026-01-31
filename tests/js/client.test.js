import { ApiClient, DEFAULT_TIMEOUT, MAX_RETRIES, RETRY_DELAY } from '@/api/client'

describe('ApiClient', () => {
  let client

  beforeEach(() => {
    global.fetch = jest.fn()
    global.window.wpSmsSettings = {
      apiUrl: 'http://localhost/wp-json/wpsms/v1/',
      nonce: 'test-nonce',
    }
    client = new ApiClient()
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  describe('constructor', () => {
    test('initializes with correct baseUrl and nonce', () => {
      expect(client.baseUrl).toBe('http://localhost/wp-json/wpsms/v1/')
      expect(client.nonce).toBe('test-nonce')
    })
  })

  describe('exported constants', () => {
    test('exports correct default values', () => {
      expect(DEFAULT_TIMEOUT).toBe(30000)
      expect(MAX_RETRIES).toBe(3)
      expect(RETRY_DELAY).toBe(1000)
    })
  })

  describe('buildQueryString', () => {
    test('returns empty string for empty params', () => {
      expect(client.buildQueryString({})).toBe('')
      expect(client.buildQueryString(null)).toBe('')
      expect(client.buildQueryString(undefined)).toBe('')
    })

    test('builds query string from simple params', () => {
      const result = client.buildQueryString({ page: 1, per_page: 10 })
      expect(result).toBe('?page=1&per_page=10')
    })

    test('handles array parameters', () => {
      const result = client.buildQueryString({ ids: [1, 2, 3] })
      expect(result).toBe('?ids%5B%5D=1&ids%5B%5D=2&ids%5B%5D=3')
    })

    test('ignores null, undefined, and empty string values', () => {
      const result = client.buildQueryString({
        valid: 'value',
        empty: '',
        nullVal: null,
        undefinedVal: undefined,
      })
      expect(result).toBe('?valid=value')
    })
  })

  describe('isNetworkError', () => {
    test('identifies AbortError as network error', () => {
      const error = new Error('Aborted')
      error.name = 'AbortError'
      expect(client.isNetworkError(error)).toBe(true)
    })

    test('identifies TypeError as network error', () => {
      const error = new TypeError('Failed to fetch')
      expect(client.isNetworkError(error)).toBe(true)
    })

    test('identifies network-related message as network error', () => {
      expect(client.isNetworkError(new Error('network error'))).toBe(true)
      expect(client.isNetworkError(new Error('fetch failed'))).toBe(true)
    })

    test('does not identify regular errors as network errors', () => {
      expect(client.isNetworkError(new Error('Server error'))).toBe(false)
    })
  })

  describe('safeParseJson', () => {
    test('parses valid JSON response', async () => {
      const mockResponse = {
        text: async () => '{"key": "value"}',
      }
      const result = await client.safeParseJson(mockResponse)
      expect(result).toEqual({ key: 'value' })
    })

    test('returns null for empty response', async () => {
      const mockResponse = {
        text: async () => '',
      }
      const result = await client.safeParseJson(mockResponse)
      expect(result).toBeNull()
    })

    test('returns null for invalid JSON', async () => {
      const mockResponse = {
        text: async () => 'not json',
      }
      const consoleSpy = jest.spyOn(console, 'warn').mockImplementation()
      const result = await client.safeParseJson(mockResponse)
      expect(result).toBeNull()
      consoleSpy.mockRestore()
    })
  })

  describe('GET request', () => {
    test('makes GET request with correct headers', async () => {
      const mockResponse = { data: { id: 1 } }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      await client.get('endpoint')

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/endpoint',
        expect.objectContaining({
          method: 'GET',
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'X-WP-Nonce': 'test-nonce',
          }),
        })
      )
    })

    test('appends query parameters', async () => {
      const mockResponse = { data: [] }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      await client.get('endpoint', { page: 1, search: 'test' })

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('endpoint?page=1&search=test'),
        expect.any(Object)
      )
    })

    test('accepts custom timeout option', async () => {
      const mockResponse = { data: {} }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      await client.get('endpoint', {}, { timeout: 5000 })

      // Verify fetch was called (timeout is handled internally)
      expect(global.fetch).toHaveBeenCalled()
    })
  })

  describe('POST request', () => {
    test('makes POST request with JSON body', async () => {
      const mockResponse = { data: { success: true } }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      const postData = { name: 'test', value: 123 }
      await client.post('endpoint', postData)

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/endpoint',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(postData),
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
          }),
        })
      )
    })

    test('accepts custom timeout option', async () => {
      const mockResponse = { data: {} }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      await client.post('endpoint', {}, { timeout: 10000 })

      expect(global.fetch).toHaveBeenCalled()
    })
  })

  describe('PUT request', () => {
    test('makes PUT request with JSON body', async () => {
      const mockResponse = { data: { updated: true } }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      const putData = { id: 1, name: 'updated' }
      await client.put('endpoint/1', putData)

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/endpoint/1',
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify(putData),
        })
      )
    })
  })

  describe('DELETE request', () => {
    test('makes DELETE request', async () => {
      const mockResponse = { data: { deleted: true } }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      await client.delete('endpoint/1')

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/endpoint/1',
        expect.objectContaining({
          method: 'DELETE',
        })
      )
    })
  })

  describe('upload request', () => {
    test('uploads FormData with longer timeout', async () => {
      const mockResponse = { data: { file_id: 1 } }
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify(mockResponse),
      })

      const formData = new FormData()
      formData.append('file', new Blob(['test']), 'test.csv')

      await client.upload('upload', formData)

      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost/wp-json/wpsms/v1/upload',
        expect.objectContaining({
          method: 'POST',
          body: formData,
        })
      )

      // Verify Content-Type is NOT set (browser sets it for FormData)
      const callArgs = global.fetch.mock.calls[0][1]
      expect(callArgs.headers['Content-Type']).toBeUndefined()
    })
  })

  describe('error handling', () => {
    test('throws error with message from API error response', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        text: async () => JSON.stringify({ error: { message: 'Bad request' } }),
      })

      await expect(client.get('endpoint')).rejects.toThrow('Bad request')
    })

    test('throws error with message from response', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 404,
        text: async () => JSON.stringify({ message: 'Not found' }),
      })

      await expect(client.get('endpoint')).rejects.toThrow('Not found')
    })

    test('throws HTTP status error when no message available', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        text: async () => '',
      })

      await expect(client.get('endpoint')).rejects.toThrow('HTTP error! status: 500')
    })

    test('throws error for empty response body', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => '',
      })

      await expect(client.get('endpoint')).rejects.toThrow('Empty response from server')
    })

    test('throws API-level error from success response', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        text: async () => JSON.stringify({ error: { message: 'API Error' } }),
      })

      await expect(client.get('endpoint')).rejects.toThrow('API Error')
    })

    test('provides user-friendly message on timeout', async () => {
      const abortError = new Error('The operation was aborted')
      abortError.name = 'AbortError'

      // Mock all retry attempts
      global.fetch.mockRejectedValue(abortError)

      await expect(client.get('endpoint')).rejects.toThrow(
        'Request timed out. Please check your connection and try again.'
      )
    }, 20000)

    test('provides user-friendly message on network error', async () => {
      const networkError = new TypeError('Failed to fetch')

      // Mock all retry attempts
      global.fetch.mockRejectedValue(networkError)

      await expect(client.get('endpoint')).rejects.toThrow(
        'Network error. Please check your internet connection.'
      )
    }, 20000)
  })

  describe('retry logic', () => {
    test('retries on network error up to MAX_RETRIES times', async () => {
      const networkError = new TypeError('Failed to fetch')
      const mockResponse = { data: { success: true } }

      // Fail twice, then succeed
      global.fetch
        .mockRejectedValueOnce(networkError)
        .mockRejectedValueOnce(networkError)
        .mockResolvedValueOnce({
          ok: true,
          text: async () => JSON.stringify(mockResponse),
        })

      const result = await client.get('endpoint')

      expect(global.fetch).toHaveBeenCalledTimes(3)
      expect(result).toEqual(mockResponse)
    }, 20000)

    test('does not retry on non-network errors', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        text: async () => JSON.stringify({ message: 'Bad request' }),
      })

      await expect(client.get('endpoint')).rejects.toThrow('Bad request')
      expect(global.fetch).toHaveBeenCalledTimes(1)
    })

    test('gives up after MAX_RETRIES attempts', async () => {
      const networkError = new TypeError('Failed to fetch')
      global.fetch.mockRejectedValue(networkError)

      await expect(client.get('endpoint')).rejects.toThrow()
      expect(global.fetch).toHaveBeenCalledTimes(MAX_RETRIES + 1)
    }, 30000)
  })

  describe('sleep utility', () => {
    test('resolves after specified delay', async () => {
      jest.useFakeTimers()
      const sleepPromise = client.sleep(1000)
      jest.advanceTimersByTime(1000)
      await sleepPromise
      jest.useRealTimers()
    })
  })
})
