import { queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'
import type {
  GetSettingsValuesByGroupParams,
  GetSettingsValuesByGroupResponse,
} from '@/types/settings/get-group-values'

// new
export function getSettingsValuesByGroup(params: GetSettingsValuesByGroupParams) {
  return queryOptions({
    queryKey: ['settings-values-by-group', params],
    queryFn: async () => {
      const url = `/settings/values/group/${params.groupName}`
      return clientRequest.get<GetSettingsValuesByGroupResponse>(url)
    },
  })
}
