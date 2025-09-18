import type { UseMutationOptions } from '@tanstack/react-query'

type SaveSettingsValuesBody = Record<string, unknown>

interface SaveSettingsValuesResponse {
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

interface SaveSettingsValuesParams {
  groupName?: SettingGroupName
  include_hidden?: boolean
}
