import { useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { formatLabel } from '@/lib/constants';
import type { ReportsResponse } from '@/lib/api';

interface ChannelUsageProps {
  data: ReportsResponse;
}

function MethodChart({ title, description, items }: { title: string; description: string; items: { name: string; count: number }[] }) {
  if (items.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">No data for this period</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="h-56">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={items} margin={{ top: 4, right: 12, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border/50" vertical={false} />
              <XAxis dataKey="name" fontSize={12} tickLine={false} axisLine={false} />
              <YAxis fontSize={12} tickLine={false} axisLine={false} allowDecimals={false} />
              <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8, border: 'none', backgroundColor: 'oklch(0.147 0.004 49.25)', color: '#fff' }} itemStyle={{ color: '#fff' }} />
              <Bar dataKey="count" name="Count" fill="hsl(var(--chart-1))" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}

export function ChannelUsage({ data }: ChannelUsageProps) {
  const loginItems = useMemo(
    () => data.channel_usage.login_methods.map((m) => ({ name: formatLabel(m.method), count: m.count })),
    [data.channel_usage.login_methods],
  );

  const mfaItems = useMemo(
    () => data.channel_usage.mfa_methods.map((m) => ({ name: formatLabel(m.method), count: m.count })),
    [data.channel_usage.mfa_methods],
  );

  const socialItems = useMemo(
    () => data.channel_usage.social_providers.map((p) => ({ name: formatLabel(p.provider), count: p.count })),
    [data.channel_usage.social_providers],
  );

  const hasSocial = socialItems.length > 0;

  return (
    <div className={`grid gap-4 ${hasSocial ? 'md:grid-cols-3' : 'md:grid-cols-2'}`}>
      <MethodChart title="Login Methods" description="How users authenticate" items={loginItems} />
      <MethodChart title="MFA Methods" description="Active MFA enrollments by type" items={mfaItems} />
      {hasSocial && (
        <MethodChart title="Social Providers" description="Social login usage by provider" items={socialItems} />
      )}
    </div>
  );
}
