interface SaveSettingsResponse {
  success: boolean
  data: {
    saved_keys: string[]
  }
}

interface ValidationErrorResponse {
  code: string
  message: string
  data: {
    status: 422
    fields: Record<string, string>
  }
}

interface ApiError {
  code: string
  message: string
  data?: any
}

export class ValidationError extends Error {
  constructor(
    message: string,
    public fields: Record<string, string>
  ) {
    super(message)
    this.name = 'ValidationError'
  }
}

export class SettingsApiService {
  async saveSettings(settings: Record<string, any>, addon?: string | null): Promise<SaveSettingsResponse> {
    try {
      const wpSmsData = window.WP_SMS_DATA
      const url = `${wpSmsData.restUrl}settings/save`
      
      console.log('Saving settings to:', url, 'with addon:', addon)
      
      // Prepare the request body with addon parameter
      const requestBody = {
        settings: settings,
        addon: addon
      }
      
      const response = await fetch(url, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': wpSmsData.nonce,
        },
        body: JSON.stringify(requestBody),
      })

      const data = await response.json()

      if (!response.ok) {
        if (response.status === 422) {
          // Validation error
          const validationError = data as ValidationErrorResponse
          throw new ValidationError(
            validationError.message,
            validationError.data.fields
          )
        } else {
          // Other API error
          const apiError = data as ApiError
          throw new Error(apiError.message || 'Failed to save settings')
        }
      }

      return data as SaveSettingsResponse
    } catch (error) {
      if (error instanceof Error) {
        throw error
      }
      throw new Error('Network error occurred while saving settings')
    }
  }
}

// Create a singleton instance
export const settingsApi = new SettingsApiService() 