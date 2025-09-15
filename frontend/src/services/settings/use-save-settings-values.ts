import { useMutation } from '@tanstack/react-query'
import { useParams } from '@tanstack/react-router'
import { toast } from 'sonner'

import { useInvalidateQuery } from '@/hooks/use-invalidate-query'
import { clientRequest } from '@/lib/client-request'
import type {
  SaveSettingsValuesBody,
  SaveSettingsValuesParams,
  SaveSettingsValuesResponse,
  UseSaveSettingsValuesType,
} from '@/types/settings/save-values'

import { getGroupSchemaOptions } from './get-group-schema-options'
import { getGroupValuesOptions } from './get-group-values-options'
import { getSchemaByGroup } from './get-schema-by-group'
import { getSettingsValuesByGroup } from './get-settings-values-by-group'

export function useSaveSettingsValues(options?: UseSaveSettingsValuesType['options']) {
  const { onSuccess, ...restOptions } = options ?? {}

  const { name } = useParams({ from: '/settings/_layout/$name' })

  const { invalidateQuery: refreshGroupValues } = useInvalidateQuery(
    getGroupValuesOptions({ params: { groupName: name ?? 'general' } }).queryKey
  )

  const { invalidateQuery: refreshGroupSchema } = useInvalidateQuery(
    getGroupSchemaOptions({ params: { groupName: name ?? 'general' } }).queryKey
  )

  return useMutation({
    mutationFn: async (body: UseSaveSettingsValuesType['body']) => {
      const url = '/settings/save'

      const response = await clientRequest.put<UseSaveSettingsValuesType['response']>(url, body)

      return response.data
    },
    onSuccess: async (...args) => {
      try {
        await refreshGroupValues()
        await refreshGroupSchema()

        toast.success('Settings saved successfully')
      } catch {
        toast.info('Settings saved but form refresh failed')
      }

      onSuccess?.(...args)
    },
    ...restOptions,
  })
}

export function useNewSaveSettingsValues(params: SaveSettingsValuesParams) {
  const { invalidateQuery: invalidateGetSchemaByGroup } = useInvalidateQuery(
    getSchemaByGroup({
      groupName: params.groupName || 'general',
      include_hidden: params.include_hidden,
    }).queryKey
  )

  const { invalidateQuery: invalidateGetSettingsValuesByGroup } = useInvalidateQuery(
    getSettingsValuesByGroup({
      groupName: params.groupName || 'general',
    }).queryKey
  )

  return useMutation({
    mutationFn: (body: SaveSettingsValuesBody) => clientRequest.put<SaveSettingsValuesResponse>('/settings/save', body),
    onSuccess: async () => {
      try {
        await invalidateGetSchemaByGroup()
        await invalidateGetSettingsValuesByGroup()

        toast.success('Settings saved successfully')
      } catch {
        toast.info('Settings saved but form refresh failed')
      }
    },
    onError: () => toast.error('Something went wrong!'),
  })
}
