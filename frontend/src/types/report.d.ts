interface GetReportConfigParams {
  slug: 'activity-overview'
}

interface BaseFilter {
  key: string
  label: string
  default?: string
}

interface DateRangeFilter extends BaseFilter {
  type: 'date-range'
  presets: Record<string, string>
}

interface RadioFilter extends BaseFilter {
  type: 'radio'
  options: Record<string, string>
}

interface SelectFilter extends BaseFilter {
  type: 'select'
  searchable?: boolean
  options: Record<string, string>
}

interface MultiSelectFilter extends BaseFilter {
  type: 'multi-select'
  options: Record<string, string>
}

type ReportFilter = DateRangeFilter | RadioFilter | SelectFilter | MultiSelectFilter

interface GetReportConfigResponse {
  success: boolean
  data: {
    slug: string
    label: string
    description: string
    filters: ReportFilter[]
    widgets: {
      id: string
      type: 'kpi' | 'funnel' | 'chart' | 'map'
      label: string
      layout: {
        row: number
        col: number
        span: number
      }
    }[]
  }
}

interface GetReportDataParams {
  slug: 'activity-overview'
  filters?: Record<string, string | string[] | number | null>
}

interface KPI {
  key: string
  label: string
  value: number
  format: 'number' | 'percentage' | 'seconds'
}

interface HealthSnapshotData {
  kpis: KPI[]
}

interface FunnelStage {
  label: string
  count: number
  dropoffPercentage?: number
}

interface JourneyFunnelsData {
  loginFunnel: {
    stages: FunnelStage[]
  }
  registrationFunnel: {
    stages: FunnelStage[]
  }
}

interface ChartDataset {
  label: string
  data: number[]
  backgroundColor: string
  borderColor?: string
}

interface VolumeOverTimeData {
  labels: string[]
  datasets: ChartDataset[]
}

interface MethodMixData {
  labels: string[]
  data: number[]
  colors: string[]
}

interface DeliveryQualityData {
  labels: string[]
  datasets: ChartDataset[]
}

interface CountryData {
  code: string
  attempts: number
  success: number
  successRate: number
  avgDuration: number
  topChannel: string
  topChannelPercentage: number
}

interface GeoHeatmapData {
  countries: CountryData[]
}

interface GetReportDataResponse {
  success: boolean
  data: {
    health_snapshot: HealthSnapshotData
    journey_funnels: JourneyFunnelsData
    volume_over_time: VolumeOverTimeData
    method_mix: MethodMixData
    delivery_quality: DeliveryQualityData
    geo_heatmap: GeoHeatmapData
  }
}
