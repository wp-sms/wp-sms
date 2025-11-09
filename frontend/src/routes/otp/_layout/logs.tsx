import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { type PaginationState, type SortingState } from '@tanstack/react-table'
import { useMemo, useState } from 'react'

import { DataTable } from '@/components/data-table'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { SettingsSchemaSkeleton } from '@/components/ui/skeleton'
import { createLogColumnsFromConfig } from '@/lib/create-log-columns'
import { getLogConfig } from '@/services/logs/get-log-config'
import { getLogData } from '@/services/logs/get-log-data'

import { LogFilters } from './-components/log-filters'

export const Route = createFileRoute('/otp/_layout/logs')({
  component: RouteComponent,
  pendingComponent: () => <SettingsSchemaSkeleton />,
})

function RouteComponent() {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  })
  const [sorting, setSorting] = useState<SortingState>([])
  const [filterValues, setFilterValues] = useState<Record<string, string | string[] | number | null>>({})
  const [appliedFilters, setAppliedFilters] = useState<Record<string, string | string[] | number | null>>({})

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
      sorts: sorting.map((sort) => ({
        column: sort.id,
        direction: sort.desc ? 'DESC' : 'ASC',
      })),
      filters: appliedFilters,
    })
  )

  const columns = useMemo(() => createLogColumnsFromConfig(configResult.data.columns), [configResult.data.columns])
  const defaultVisibility = useMemo(() => {
    if (!configResult.data?.columns) return {}

    return configResult.data.columns.reduce(
      (acc, column) => {
        acc[column.key] = !!column.visisble
        return acc
      },
      {} as Record<string, boolean>
    )
  }, [configResult.data?.columns])

  const logDataResult = logDataResponse?.data

  const handleApplyFilters = () => {
    setAppliedFilters(filterValues)
    setPagination({ pageIndex: 0, pageSize: pagination.pageSize })
  }

  const handleResetFilters = () => {
    setFilterValues({})
    setAppliedFilters({})
    setPagination({ pageIndex: 0, pageSize: pagination.pageSize })
  }

  return (
    <Card className="flex flex-col gap-y-8 w-full">
      <CardHeader>
        <div className="flex items-start justify-between">
          <div>
            <CardTitle>{configResult.data.label}</CardTitle>
            <CardDescription>{configResult.data.description}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable
          columns={columns}
          data={logDataResult?.data.rows ?? []}
          pagination={pagination}
          onPaginationChange={setPagination}
          rowCount={logDataResult?.data.totalCount ?? 0}
          isLoading={isLoading || isPlaceholderData}
          sorting={sorting}
          onSortingChange={setSorting}
          defaultVisibility={defaultVisibility}
          extra={
            <LogFilters
              filters={configResult.data.filters}
              values={filterValues}
              onChange={setFilterValues}
              onApply={handleApplyFilters}
              onReset={handleResetFilters}
            />
          }
        />
      </CardContent>
    </Card>
  )
}
