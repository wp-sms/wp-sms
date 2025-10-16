import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import { cn } from '@/lib/utils'

interface VolumeChartWidgetProps {
  label: string
  data: VolumeOverTimeData
  className?: string
}

export function VolumeChartWidget({ label, data, className }: VolumeChartWidgetProps) {
  // Transform data from Chart.js format to Recharts format
  const chartData = data.labels.map((label, index) => {
    const point: Record<string, string | number> = { time: label }
    data.datasets.forEach((dataset) => {
      point[dataset.label] = dataset.data[index]
    })
    return point
  })

  // Generate chart config from datasets
  const chartConfig = data.datasets.reduce(
    (config, dataset, index) => {
      const key = dataset.label
      config[key] = {
        label: dataset.label,
        color: dataset.borderColor || dataset.backgroundColor || `hsl(var(--chart-${index + 1}))`,
      }
      return config
    },
    {} as Record<string, { label: string; color: string }>
  )

  return (
    <Card className={cn('h-full', className)}>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="h-[300px]">
          <AreaChart
            data={chartData}
            margin={{
              left: 12,
              right: 12,
            }}
          >
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey="time" tickLine={false} axisLine={false} tickMargin={8} className="text-xs" />
            <YAxis tickLine={false} axisLine={false} tickMargin={8} className="text-xs" />
            <ChartTooltip content={<ChartTooltipContent />} />
            <ChartLegend content={<ChartLegendContent />} />
            {data.datasets.map((dataset) => (
              <Area
                key={dataset.label}
                dataKey={dataset.label}
                type="monotone"
                fill={`var(--color-${dataset.label})`}
                fillOpacity={0.2}
                stroke={`var(--color-${dataset.label})`}
                stackId="a"
              />
            ))}
          </AreaChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
