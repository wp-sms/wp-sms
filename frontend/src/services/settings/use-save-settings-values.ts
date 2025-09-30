import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'

import { useInvalidateQuery } from '@/hooks/use-invalidate-query'
import { clientRequest } from '@/lib/client-request'
import type {
  SaveSettingsValuesBody,
  SaveSettingsValuesParams,
  SaveSettingsValuesResponse,
} from '@/types/settings/save-values'

import { getSchemaByGroup } from './get-schema-by-group'
import { getSettingsValuesByGroup } from './get-settings-values-by-group'

export function useSaveSettingsValues(params: SaveSettingsValuesParams) {
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
