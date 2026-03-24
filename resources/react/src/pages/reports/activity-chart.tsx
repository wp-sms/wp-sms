import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import type { ReportsResponse } from '@/lib/api';

interface ActivityChartProps {
  data: ReportsResponse;
}

function formatTick(v: string): string {
  return new Date(v + 'T00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function formatTooltipLabel(v: string): string {
  return new Date(v + 'T00:00').toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
}

export function ActivityChart({ data }: ActivityChartProps) {
  const timeline = data.auth_activity.timeline;

  if (timeline.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Auth Activity</CardTitle>
          <CardDescription>No activity data for this period</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Auth Activity</CardTitle>
        <CardDescription>Logins, failures, and registrations over time</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="h-72">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={timeline} margin={{ top: 4, right: 12, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id="loginsFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="hsl(var(--chart-1))" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="hsl(var(--chart-1))" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="failuresFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="hsl(var(--chart-2))" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="hsl(var(--chart-2))" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="regFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="hsl(var(--chart-3))" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="hsl(var(--chart-3))" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border/50" vertical={false} />
              <XAxis
                dataKey="date"
                fontSize={12}
                tickLine={false}
                axisLine={false}
                tickFormatter={formatTick}
              />
              <YAxis fontSize={12} tickLine={false} axisLine={false} allowDecimals={false} />
              <Tooltip
                contentStyle={{ fontSize: 12, borderRadius: 8, border: 'none', backgroundColor: 'oklch(0.147 0.004 49.25)', color: '#fff' }}
                labelStyle={{ color: '#fff', marginBottom: 4 }}
                itemStyle={{ color: '#fff' }}
                labelFormatter={formatTooltipLabel}
              />
              <Legend verticalAlign="top" height={36} iconType="circle" />
              <Area type="monotone" dataKey="logins" name="Logins" stroke="hsl(var(--chart-1))" fill="url(#loginsFill)" strokeWidth={2} />
              <Area type="monotone" dataKey="failures" name="Failures" stroke="hsl(var(--chart-2))" fill="url(#failuresFill)" strokeWidth={2} />
              <Area type="monotone" dataKey="registrations" name="Registrations" stroke="hsl(var(--chart-3))" fill="url(#regFill)" strokeWidth={2} />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}
