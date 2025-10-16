import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { getColSpanClass, getColStartClass } from '@/lib/utils'
import { getReportConfig } from '@/services/reports/get-report-config'
import { getReportData } from '@/services/reports/get-report-data'

import { ActivityFilters } from './-components/activity-filters'

export const Route = createFileRoute('/otp/_layout/activity')({
  component: RouteComponent,
})

function RouteComponent() {
  const [filterValues, setFilterValues] = useState<Record<string, string | string[] | number | null>>({})
  const [appliedFilters, setAppliedFilters] = useState<Record<string, string | string[] | number | null>>({})

  const {
    data: { data: reportConfig },
  } = useSuspenseQuery(getReportConfig({ slug: 'activity-overview' }))

  const { data: reportDataResponse } = useQuery(
    getReportData({
      slug: 'activity-overview',
      filters: appliedFilters,
    })
  )

  const reportData = reportDataResponse?.data.data

  const handleApplyFilters = () => {
    setAppliedFilters(filterValues)
  }

  const handleResetFilters = () => {
    setFilterValues({})
    setAppliedFilters({})
  }

  console.log(reportData)

  return (
    <Card className="flex flex-col">
      <CardHeader>
        <div className="flex items-start justify-between">
          <div>
            <CardTitle>{reportConfig.data.label}</CardTitle>
            <CardDescription>{reportConfig.data.description}</CardDescription>
          </div>
          <ActivityFilters
            filters={reportConfig.data.filters}
            values={filterValues}
            onChange={setFilterValues}
            onApply={handleApplyFilters}
            onReset={handleResetFilters}
          />
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-12 gap-4">
          {reportConfig.data.widgets.map((widget) => (
            <div
              key={widget.id}
              className={`${getColStartClass(widget.layout.col)} ${getColSpanClass(widget.layout.span)}`}
            >
              <Card>
                <CardHeader>
                  <CardTitle>{widget.label}</CardTitle>
                </CardHeader>
              </Card>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
