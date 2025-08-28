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
  options: Partial<UseQueryOptions<any, any, GetGroupValuesResponse, any>> & GetGroupValuesParams
  response: GetGroupValuesResponse
}
