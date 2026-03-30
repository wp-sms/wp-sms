import { __ } from '@wordpress/i18n';
import { EmptyState } from '@/components/ui/empty-state';
import { PageSection } from '@/components/ui/page-section';
import { Badge } from '@/components/ui/badge';
import { HeartPulse, ListTodo, XCircle, Clock, Megaphone, ServerCrash } from 'lucide-react';
import type { HealthCheck, SystemHealthResponse } from '@/lib/api';
import { HealthCheckRow } from './health-check-row';
import { HeartbeatSection } from './heartbeat-section';
import { QueueOverview } from './queue-overview';
import { FailedJobsSection } from './failed-jobs-section';
import { ActivityFeed } from './activity-feed';
import { ActiveCampaignsCard } from './active-campaigns-card';

interface BackgroundSectionProps {
  checks: HealthCheck[];
  data: SystemHealthResponse;
  onMutate: () => void;
}

export function BackgroundSection({ checks, data, onMutate }: BackgroundSectionProps) {
  const asCheck = checks.find(c => c.id === 'bg_action_scheduler');
  const asAvailable = asCheck?.status !== 'fail';

  return (
    <div className="space-y-4">
      {/* New health check rows at top */}
      <div className="space-y-1.5">
        {checks.map((check, i) => (
          <HealthCheckRow key={check.id} check={check} index={i} />
        ))}
      </div>

      {/* Existing sub-sections */}
      {!asAvailable ? (
        <EmptyState
          icon={ServerCrash}
          title={__('Action Scheduler is not available', 'wp-sms')}
          description={__('Background processing requires Action Scheduler. Deactivate and reactivate the plugin.', 'wp-sms')}
          compact
        />
      ) : (
        <div className="space-y-4">
          <PageSection
            icon={HeartPulse}
            title={__('Scheduled Tasks', 'wp-sms')}
            description={__('Health of recurring background tasks', 'wp-sms')}
            defaultOpen
            collapsible
            storageKey="system-heartbeat"
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
          >
            <FailedJobsSection data={data.failed_jobs} onMutate={onMutate} />
          </PageSection>

          <PageSection
            icon={Clock}
            title={__('Recent Activity', 'wp-sms')}
            description={__('Last completed and failed jobs', 'wp-sms')}
            defaultOpen={false}
            collapsible
            storageKey="system-activity"
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
            >
              <ActiveCampaignsCard data={data.active_campaigns} />
            </PageSection>
          )}
        </div>
      )}
    </div>
  );
}
