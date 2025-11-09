import { Bar, BarChart, CartesianGrid, XAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import { cn } from '@/lib/utils'

interface DeliveryQualityWidgetProps {
  label: string
  data: DeliveryQualityData
  className?: string
}

export function DeliveryQuality({ label, data, className }: DeliveryQualityWidgetProps) {
  const chartData = data.labels.map((label, index) => {
    const point: Record<string, string | number> = { category: label }
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
        color: dataset.backgroundColor || dataset.borderColor || `hsl(var(--chart-${index + 1}))`,
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
          <BarChart accessibilityLayer data={chartData}>
            <CartesianGrid vertical={false} />
            <XAxis dataKey="category" tickLine={false} tickMargin={10} axisLine={false} />
            <ChartTooltip content={<ChartTooltipContent hideLabel />} />
            <ChartLegend content={<ChartLegendContent payload={[]} verticalAlign="top" />} />
            {data.datasets.map((dataset, index) => (
              <Bar
                key={dataset.label}
                dataKey={dataset.label}
                stackId="a"
                fill={dataset.backgroundColor || dataset.borderColor || `hsl(var(--chart-${index + 1}))`}
                radius={index === 0 ? [0, 0, 4, 4] : index === data.datasets.length - 1 ? [4, 4, 0, 0] : 0}
              />
            ))}
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
