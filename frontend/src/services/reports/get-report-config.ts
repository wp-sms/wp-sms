import { queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'

export function getReportConfig(params: GetReportConfigParams) {
  return queryOptions({
    queryKey: ['report-config', params],
    queryFn: async () => {
      const url = `/reports/${params.slug}/config`
      return clientRequest.get<GetReportConfigResponse>(url)
    },
  })
}
