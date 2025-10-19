import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

interface KpiWidgetProps {
  kpis: KPI[]
}

function formatValue(value: number, format: KPI['format']) {
  switch (format) {
    case 'percentage':
      return `${value.toFixed(1)}%`
    case 'seconds':
      if (value < 60) {
        return `${value.toFixed(1)}s`
      }
      return `${(value / 60).toFixed(1)}m`
    case 'number':
    default:
      return value.toLocaleString()
  }
}

export function Kpi({ kpis }: KpiWidgetProps) {
  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 *:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card dark:*:data-[slot=card]:bg-card *:data-[slot=card]:bg-gradient-to-t *:data-[slot=card]:shadow-xs @xl/main:grid-cols-2 @5xl/main:grid-cols-4">
      {kpis.map((kpi) => (
        <Card key={kpi.key}>
          <CardHeader>
            <CardDescription>{kpi.label}</CardDescription>
            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">
              {formatValue(kpi.value, kpi.format)}
            </CardTitle>
          </CardHeader>
        </Card>
      ))}
    </div>
  )
}
