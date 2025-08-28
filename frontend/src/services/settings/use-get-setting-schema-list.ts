import { useQuery } from '@tanstack/react-query'
import { getSettingSchemaListOptions } from './get-setting-schema-list-options'

export function useGetSettingSchemaList() {
  return useQuery(getSettingSchemaListOptions())
}
