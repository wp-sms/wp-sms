import { useQuery } from '@tanstack/react-query'
import { getGroupSchemaOptions } from './get-group-schema-options'
import type { UseGetGroupSchemaType } from '@/types/settings/group-schema'

export function useGetGroupSchema(options?: UseGetGroupSchemaType['options']) {
  return useQuery(getGroupSchemaOptions(options))
}
