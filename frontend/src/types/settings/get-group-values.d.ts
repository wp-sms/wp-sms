import type { UseQueryOptions } from '@tanstack/react-query'

type GetGroupValuesParams = {
  params?: Partial<{
    groupName: string
  }>
}

type GroupValues = Record<string, unknown>

type GetGroupValuesResponse = {
  success: boolean
  data: GroupValues
}

type UseGetGroupValuesType = {
  options: Partial<UseQueryOptions<unknown, unknown, GetGroupValuesResponse, unknown>> & GetGroupValuesParams
  response: GetGroupValuesResponse
}

interface GetSettingsValuesByGroupParams {
  groupName: SettingGroupName
}

interface GetSettingsValuesByGroupResponse {
  success: boolean
  data: GroupValues
}
