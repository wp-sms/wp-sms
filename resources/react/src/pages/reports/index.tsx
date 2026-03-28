import { __ } from '@wordpress/i18n';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, BarChart3 } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { useReports } from '@/hooks/use-reports';
import { SummaryCards } from './summary-cards';
import { ActivityChart } from './activity-chart';
import { ChannelUsage } from './channel-usage';
import { SecurityAlerts } from './security-alerts';

const RANGES = [
  { value: '7', label: '7 days' },
  { value: '30', label: '30 days' },
  { value: '90', label: '90 days' },
];

export function ReportsPage({ embedded }: { embedded?: boolean }) {
  const { data, loading, error, range, setRange } = useReports();

  return (
    <div className="space-y-4">
      {!embedded && <PageHeader icon={BarChart3} title={__('Reports', 'wp-sms')} />}
      <Tabs value={String(range)} onValueChange={(v) => setRange(Number(v))}>
        <TabsList>
          {RANGES.map((r) => (
            <TabsTrigger key={r.value} value={r.value}>{r.label}</TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {loading && (
        <div className="space-y-4">
          <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-28 w-full" />
            ))}
          </div>
          <Skeleton className="h-72 w-full" />
          <div className="grid gap-4 md:grid-cols-2">
            <Skeleton className="h-56 w-full" />
            <Skeleton className="h-56 w-full" />
          </div>
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {!loading && data && (
        <>
          <SummaryCards data={data} />
          <ActivityChart data={data} />
          <ChannelUsage data={data} />
          <SecurityAlerts data={data} />
        </>
      )}
    </div>
  );
}
