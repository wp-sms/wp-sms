import { queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'
import type { GetSchemaByGroupParams, GetSchemaByGroupResponse } from '@/types/settings/group-schema'

export function getSchemaByGroup(params: GetSchemaByGroupParams) {
  return queryOptions({
    queryKey: ['schema-by-group', params],
    queryFn: async () => {
      const url = `/settings/schema/group/${params.groupName}`
      return clientRequest.get<GetSchemaByGroupResponse>(url, {
        params: {
          include_hidden: params.include_hidden || false,
        },
      })
    },
  })
}
