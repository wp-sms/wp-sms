import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { getColSpanClass, getColStartClass } from '@/lib/utils'
import { getReportConfig } from '@/services/reports/get-report-config'
import { getReportData } from '@/services/reports/get-report-data'

export const Route = createFileRoute('/otp/_layout/activity')({
  component: RouteComponent,
})

function RouteComponent() {
  const {
    data: { data: reportConfig },
  } = useSuspenseQuery(getReportConfig({ slug: 'activity-overview' }))

  const {
    data: { data: reportData },
  } = useSuspenseQuery(getReportData({ slug: 'activity-overview' }))

  console.log(reportData)

  return (
    <Card className="flex flex-col">
      <CardHeader>
        <CardTitle>{reportConfig.data.label}</CardTitle>
        <CardDescription>{reportConfig.data.description}</CardDescription>
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
