import { useQuery } from '@tanstack/react-query'

import type { UseGetGroupSchemaType } from '@/types/settings/group-schema'

import { getGroupSchemaOptions } from './get-group-schema-options'

export function useGetGroupSchema(options?: UseGetGroupSchemaType['options']) {
  return useQuery(getGroupSchemaOptions(options))
}
