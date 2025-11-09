import { Pie, PieChart } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { cn } from '@/lib/utils'

interface MethodMixWidgetProps {
  label: string
  data: MethodMixData
  className?: string
}

interface ChartDataItem {
  name: string
  value: number
  fill: string
}

export function MethodMix({ label, data, className }: MethodMixWidgetProps) {
  const chartData = data.labels.map((label, index) => ({
    name: label,
    value: data.data[index],
    fill: data.colors[index],
  }))

  const chartConfig = data.labels.reduce(
    (config, label, index) => {
      config[label] = {
        label: label,
        color: data.colors[index],
      }
      return config
    },
    {} as Record<string, { label: string; color: string }>
  )

  return (
    <Card className={cn('flex flex-col h-full', className)}>
      <CardHeader className="pb-0">
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 pb-0">
        <ChartContainer config={chartConfig}>
          <PieChart>
            <ChartTooltip content={<ChartTooltipContent nameKey="value" hideLabel />} />
            <Pie
              data={chartData}
              dataKey="value"
              labelLine={false}
              label={({ payload, ...props }) => {
                const chartPayload = payload as ChartDataItem
                return (
                  <text
                    cx={props.cx}
                    cy={props.cy}
                    x={props.x}
                    y={props.y}
                    textAnchor={props.textAnchor}
                    dominantBaseline={props.dominantBaseline}
                    fill="hsla(var(--foreground))"
                  >
                    {chartPayload.name}: {chartPayload.value}
                  </text>
                )
              }}
              nameKey="name"
            />
          </PieChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
