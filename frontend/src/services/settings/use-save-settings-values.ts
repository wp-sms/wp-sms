import { useMutation } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { clientRequest } from '@/lib/client-request'
import { useInvalidateQuery } from '@/hooks/use-invalidate-query'
import { getGroupValuesOptions } from './get-group-values-options'
import { getGroupSchemaOptions } from './get-group-schema-options'
import type { UseSaveSettingsValuesType } from '@/types/settings/save-values'

export function useSaveSettingsValues(options?: UseSaveSettingsValuesType['options']) {
  const { onSuccess, ...restOptions } = options ?? {}

  const { name } = useParams()

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

        toast.success('Settings saved successfully', {
          position: 'top-center',
          className: '!p-4',
        })
      } catch (error) {
        toast.info('Settings saved but form refresh failed', {
          position: 'top-center',
          className: '!p-4',
        })
      }

      onSuccess?.(...args)
    },
    ...restOptions,
  })
}
