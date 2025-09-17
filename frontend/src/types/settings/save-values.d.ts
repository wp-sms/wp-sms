import type { UseMutationOptions } from '@tanstack/react-query'

type SaveSettingsValuesBody = Record<string, any>

type SaveSettingsValuesResponse = {
  success: boolean
  data: {
    saved_keys: string[]
  }
}

type UseSaveSettingsValuesType = {
  options?: UseMutationOptions<SaveSettingsValuesResponse, Error, SaveSettingsValuesBody, unknown>
  body: SaveSettingsValuesBody
  response: SaveSettingsValuesResponse
}
