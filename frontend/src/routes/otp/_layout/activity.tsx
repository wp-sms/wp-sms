import { useQuery, useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { getColSpanClass, getColStartClass } from '@/lib/utils'
import { getReportConfig } from '@/services/reports/get-report-config'
import { getReportData } from '@/services/reports/get-report-data'

import { ActivityFilters } from './-components/activity-filters'
import { DeliveryQualityWidget } from './-components/delivery-quality-widget'
import { FunnelWidget } from './-components/funnel-widget'
import { GeoHeatmapWidget } from './-components/geo-heatmap-widget'
import { Kpi } from './-components/kpi'
import { MethodMix } from './-components/method-mix'
import { VolumeChartWidget } from './-components/volume-chart-widget'

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

  const renderWidget = (widget: (typeof reportConfig.data.widgets)[0]) => {
    if (!reportData) {
      return (
        <Card className="h-full">
          <CardHeader>
            <CardTitle>{widget.label}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center justify-center h-[300px] text-muted-foreground">Loading...</div>
          </CardContent>
        </Card>
      )
    }

    switch (widget.type) {
      case 'kpi':
        if (widget.id === 'health_snapshot' && reportData.health_snapshot) {
          return <Kpi kpis={reportData.health_snapshot.kpis} />
        }
        break

      case 'funnel':
        if (reportData.journey_funnels) {
          const funnelType = widget.id === 'login_funnel' ? 'loginFunnel' : 'registrationFunnel'
          return <FunnelWidget label={widget.label} data={reportData.journey_funnels} funnelType={funnelType} />
        }
        break

      case 'chart':
        if (widget.id === 'volume_over_time' && reportData.volume_over_time) {
          return <VolumeChartWidget label={widget.label} data={reportData.volume_over_time} />
        }
        if (widget.id === 'method_mix' && reportData.method_mix) {
          return <MethodMix label={widget.label} data={reportData.method_mix} />
        }
        if (widget.id === 'delivery_quality' && reportData.delivery_quality) {
          return <DeliveryQualityWidget label={widget.label} data={reportData.delivery_quality} />
        }
        break

      case 'map':
        if (widget.id === 'geo_heatmap' && reportData.geo_heatmap) {
          return <GeoHeatmapWidget label={widget.label} data={reportData.geo_heatmap} />
        }
        break
    }

    return (
      <Card className="h-full">
        <CardHeader>
          <CardTitle>{widget.label}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-center h-[300px] text-muted-foreground">No data available</div>
        </CardContent>
      </Card>
    )
  }

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
              {renderWidget(widget)}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
