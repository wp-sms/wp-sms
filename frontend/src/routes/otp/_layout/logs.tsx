import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useMemo } from 'react'

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
      context.queryClient.ensureQueryData(getLogData({ slug: 'auth-events', page: 1, perPage: 50 })),
    ]),
  component: RouteComponent,
  pendingComponent: () => <SettingsSchemaSkeleton />,
})

function RouteComponent() {
  const {
    data: { data: configResult },
  } = useSuspenseQuery(getLogConfig({ slug: 'auth-events' }))
  const {
    data: { data: logDataResult },
  } = useSuspenseQuery(getLogData({ slug: 'auth-events', page: 1, perPage: 50 }))

  const columns = useMemo(() => createColumnsFromConfig(configResult.data.columns), [configResult.data.columns])

  console.log({ logDataResult })

  return (
    <Card className="flex flex-col gap-y-8">
      <CardHeader>
        <CardTitle>{configResult.data.label}</CardTitle>
        <CardDescription>{configResult.data.description}</CardDescription>
      </CardHeader>
      <CardContent>
        <DataTable columns={columns} data={logDataResult.data.rows} />
      </CardContent>
    </Card>
  )
}
