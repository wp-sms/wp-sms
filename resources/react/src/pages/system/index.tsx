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

export function SystemHealth() {
  const { data, loading, error, refetch } = useSystemHealth();

  usePolling(refetch, 30_000, !loading);

  return (
    <div className="space-y-4">
      <PageHeader
        icon={Activity}
        title="System Health"
        metadata={data && (
          <span className="text-xs tabular-nums text-muted-foreground">
            Updated {formatRelativeTime(data.generated_at)}
          </span>
        )}
        actions={
          <Button variant="outline" size="sm" onClick={refetch} disabled={loading}>
            <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        }
      />

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
            title="Scheduled Tasks"
            description="Health of recurring background tasks"
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
            title="Queue Overview"
            description="Current job queue status"
            actions={
              (data.queue.totals['failed'] ?? 0) > 0 && (
                <Badge variant="destructive" dot>
                  {data.queue.totals['failed']} failed
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
            title="Failed Jobs"
            description="Jobs that encountered errors"
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
            title="Recent Activity"
            description="Last completed and failed jobs"
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
              title="Active Campaigns"
              description="Campaigns currently sending"
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
