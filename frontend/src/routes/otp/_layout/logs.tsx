import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { type PaginationState } from '@tanstack/react-table'
import { useMemo, useState } from 'react'

import { DataTable } from '@/components/data-table'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { SettingsSchemaSkeleton } from '@/components/ui/skeleton'
import { createColumnsFromConfig } from '@/lib/create-columns'
import { getLogConfig } from '@/services/logs/get-log-config'
import { getLogData } from '@/services/logs/get-log-data'

export const Route = createFileRoute('/otp/_layout/logs')({
  loader: ({ context }) =>
    Promise.all([
      context.queryClient.ensureQueryData(getLogConfig({ slug: 'auth-events' })),
      context.queryClient.ensureQueryData(getLogData({ slug: 'auth-events', page: 1, perPage: 10 })),
    ]),
  component: RouteComponent,
  pendingComponent: () => <SettingsSchemaSkeleton />,
})

function RouteComponent() {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  })

  const {
    data: { data: configResult },
  } = useSuspenseQuery(getLogConfig({ slug: 'auth-events' }))

  const {
    data: logDataResponse,
    isLoading,
    isPlaceholderData,
  } = useQuery(
    getLogData({
      slug: 'auth-events',
      page: pagination.pageIndex + 1,
      perPage: pagination.pageSize,
    })
  )

  const columns = useMemo(() => createColumnsFromConfig(configResult.data.columns), [configResult.data.columns])

  const logDataResult = logDataResponse?.data

  return (
    <Card className="flex flex-col gap-y-8">
      <CardHeader>
        <CardTitle>{configResult.data.label}</CardTitle>
        <CardDescription>{configResult.data.description}</CardDescription>
      </CardHeader>
      <CardContent className="max-w-full">
        <DataTable
          columns={columns}
          data={logDataResult?.data.rows ?? []}
          pagination={pagination}
          onPaginationChange={setPagination}
          rowCount={logDataResult?.data.totalCount ?? 0}
          isLoading={isLoading || isPlaceholderData}
        />
      </CardContent>
    </Card>
  )
}
