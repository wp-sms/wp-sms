import { queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'

export function getSettingSchemaListOptions() {
  return queryOptions({
    queryKey: ['schema-list'],
    queryFn: async () => {
      const url = '/settings/schema/list'

      const response = await clientRequest.get<UseGetSettingSchemaListType['response']>(url)

      return response.data
    },
  })
}
