import { queryOptions } from '@tanstack/react-query'
import type { UseGetGroupSchemaType } from '../../types/settings/group-schema'
import { clientRequest } from '@/lib/client-request'

export function getGroupSchemaOptions(options?: UseGetGroupSchemaType['options']) {
  const { params, ...restOptions } = options || {}

  return queryOptions({
    queryKey: ['group-schema', params],
    queryFn: async () => {
      const url = `/settings/schema/group/${params?.groupName}`

      const response = await clientRequest.get<UseGetGroupSchemaType['response']>(url)

      return response.data
    },
    enabled: Boolean(params?.groupName),
    ...restOptions,
  })
}
