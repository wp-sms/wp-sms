import { queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'

export function getLogData(params: GetLogDataParams) {
  const { slug, ...otherParams } = params

  return queryOptions({
    queryKey: ['log-data', params],
    queryFn: async () => {
      const url = `/logs/${slug}/data`
      return clientRequest.get<GetLogDataResponse>(url, {
        params: otherParams,
      })
    },
  })
}
