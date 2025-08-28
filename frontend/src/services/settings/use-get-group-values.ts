import { useQuery } from '@tanstack/react-query'
import type { UseGetGroupValuesType } from '@/types/settings/get-group-values'
import { getGroupValuesOptions } from './get-group-values-options'

export function useGetGroupValues(options?: UseGetGroupValuesType['options']) {
  return useQuery(getGroupValuesOptions(options))
}
