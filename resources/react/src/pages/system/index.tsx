import { __ } from '@wordpress/i18n';
import { useEffect, type ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { PageHeader } from '@/components/layout/page-header';
import { PageSection } from '@/components/ui/page-section';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useSystemHealth } from '@/hooks/use-system-health';
import { usePolling } from '@/hooks/use-polling';
import { HeartbeatSection } from './heartbeat-section';
import { QueueOverview } from './queue-overview';
import { FailedJobsSection } from './failed-jobs-section';
import { ActivityFeed } from './activity-feed';
import { ActiveCampaignsCard } from './active-campaigns-card';
import { Activity, AlertCircle, AlertTriangle, RefreshCw, HeartPulse, ListTodo, XCircle, Clock, Megaphone } from 'lucide-react';
import { formatRelativeTime } from '@/lib/format';

interface SystemHealthProps {
  embedded?: boolean;
  setHeaderMeta?: (node: ReactNode) => void;
  setHeaderActions?: (node: ReactNode) => void;
}

export function SystemHealth({ embedded, setHeaderMeta, setHeaderActions }: SystemHealthProps) {
  const { data, loading, error, refetch } = useSystemHealth();

  usePolling(refetch, 30_000, !loading);

  const refreshButton = (
    <Button variant="outline" size="sm" onClick={refetch} disabled={loading}>
      <RefreshCw className={`me-1.5 h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
      {__('Refresh', 'wp-sms')}
    </Button>
  );

  const timestamp = data && (
    <span className="text-xs tabular-nums text-muted-foreground">
      {__('Updated', 'wp-sms')} {formatRelativeTime(data.generated_at)}
    </span>
  );

  useEffect(() => {
    setHeaderMeta?.(timestamp ?? null);
    return () => setHeaderMeta?.(null);
  }, [data?.generated_at, setHeaderMeta]);

  useEffect(() => {
    setHeaderActions?.(refreshButton);
    return () => setHeaderActions?.(null);
  }, [loading, setHeaderActions]);

  return (
    <div className="space-y-4">
      {!embedded && (
        <PageHeader icon={Activity} title={__('System Health', 'wp-sms')} metadata={timestamp} actions={refreshButton} />
      )}
      {embedded && !setHeaderMeta && (
        <div className="flex items-center justify-between">
          {timestamp}
          {refreshButton}
        </div>
      )}

      {loading && !data && (
        <div className="space-y-4">
          <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-[104px] w-full rounded-lg" />
            ))}
          </div>
          <div className="grid gap-3 grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-20 w-full rounded-lg" />
            ))}
          </div>
          <Skeleton className="h-48 w-full rounded-lg" />
        </div>
      )}

      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {data && (
        <>
          {data.cron_health.status !== 'healthy' && data.cron_health.status !== 'unknown' && (
            <Alert
              variant={data.cron_health.status === 'error' ? 'destructive' : 'default'}
              className="animate-fade-up"
            >
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>
                {data.cron_health.status === 'error' ? 'Background processing stopped' : 'Scheduler may be delayed'}
              </AlertTitle>
              <AlertDescription>
                {data.cron_health.wp_cron_disabled
                  ? 'WP-Cron is disabled. Background processing requires an external cron job or a server-level scheduler to trigger Action Scheduler.'
                  : 'The Action Scheduler runner appears stalled. No jobs have completed in the last 10 minutes.'}
              </AlertDescription>
            </Alert>
          )}

          <PageSection
            icon={HeartPulse}
            title={__('Scheduled Tasks', 'wp-sms')}
            description={__('Health of recurring background tasks', 'wp-sms')}
            defaultOpen
            collapsible
            storageKey="system-heartbeat"
            className="animate-fade-up"
            style={{ animationDelay: '0ms' }}
          >
            <HeartbeatSection data={data.heartbeat} />
          </PageSection>

          <PageSection
            icon={ListTodo}
            title={__('Queue Overview', 'wp-sms')}
            description={__('Current job queue status', 'wp-sms')}
            actions={
              (data.queue.totals['failed'] ?? 0) > 0 && (
                <Badge variant="destructive" dot>
                  {data.queue.totals['failed']} {__('failed', 'wp-sms')}
                </Badge>
              )
            }
            defaultOpen
            collapsible
            storageKey="system-queue"
            className="animate-fade-up"
            style={{ animationDelay: '60ms' }}
          >
            <QueueOverview data={data.queue} />
          </PageSection>

          <PageSection
            icon={XCircle}
            title={__('Failed Jobs', 'wp-sms')}
            description={__('Jobs that encountered errors', 'wp-sms')}
            actions={
              data.failed_jobs.total > 0 && (
                <Badge variant="destructive">{data.failed_jobs.total}</Badge>
              )
            }
            defaultOpen={data.failed_jobs.items.length > 0}
            collapsible
            storageKey="system-failed"
            className="animate-fade-up"
            style={{ animationDelay: '120ms' }}
          >
            <FailedJobsSection data={data.failed_jobs} onMutate={refetch} />
          </PageSection>

          <PageSection
            icon={Clock}
            title={__('Recent Activity', 'wp-sms')}
            description={__('Last completed and failed jobs', 'wp-sms')}
            defaultOpen={false}
            collapsible
            storageKey="system-activity"
            className="animate-fade-up"
            style={{ animationDelay: '180ms' }}
          >
            <ActivityFeed data={data.recent_activity} />
          </PageSection>

          {data.active_campaigns.length > 0 && (
            <PageSection
              icon={Megaphone}
              title={__('Active Campaigns', 'wp-sms')}
              description={__('Campaigns currently sending', 'wp-sms')}
              defaultOpen
              collapsible
              storageKey="system-campaigns"
              className="animate-fade-up"
              style={{ animationDelay: '240ms' }}
            >
              <ActiveCampaignsCard data={data.active_campaigns} />
            </PageSection>
          )}
        </>
      )}
    </div>
  );
}
