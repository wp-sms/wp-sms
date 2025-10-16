import { keepPreviousData, queryOptions } from '@tanstack/react-query'

import { clientRequest } from '@/lib/client-request'

export function getReportData(params: GetReportDataParams) {
  const { slug, ...otherParams } = params

  return queryOptions({
    queryKey: ['report-data', params],
    queryFn: async () => {
      const url = `/reports/${slug}/data`
      return clientRequest.get<GetReportDataResponse>(url, {
        params: {
          ...(otherParams.filters ? { filters: JSON.stringify(otherParams.filters) } : {}),
        },
      })
    },
    placeholderData: keepPreviousData,
  })
}
