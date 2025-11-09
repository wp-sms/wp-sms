import { queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'

export function getLogConfig(params: GetLogConfigParams) {
  return queryOptions({
    queryKey: ['log-config', params],
    queryFn: async () => {
      const url = `/logs/${params.slug}/config`
      return clientRequest.get<GetLogConfigResponse>(url)
    },
  })
}
