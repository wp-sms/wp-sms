import { __, sprintf } from '@wordpress/i18n';
import { formatDateTime, formatRelativeTime, formatDuration, formatFutureRelativeTime } from '@/lib/format';
import { useCampaignDetail } from '@/hooks/use-campaign-detail';
import type { Campaign, CampaignRecipient, CampaignStats } from '@/lib/api';
import { MessageLogDetailPanel } from '@/components/messaging/message-log-detail-panel';
import { CampaignStatusBadge } from '@/components/campaigns/campaign-status-badge';
import { StatusBadge } from '@/components/messaging/message-badges';
import { PageHeader } from '@/components/layout/page-header';
import { PageSection } from '@/components/ui/page-section';
import { DataTable } from '@/components/ui/data-table';
import { EmptyState } from '@/components/ui/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn, pluralize } from '@/lib/utils';
import { channelLabel } from '@/components/gateway-config-form';
import {
  ArrowLeft, CheckCircle, XCircle, Clock, Users,
  Pause, Play, Ban, DollarSign,
  Info, Search, FileText, AlertTriangle,
  Repeat, Moon,
} from 'lucide-react';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { useState, useMemo } from 'react';
import { useConfirm } from '@/components/confirm-provider';

interface CampaignDetailProps {
  campaign: Campaign;
  onBack: () => void;
}

// Statuses where stats/recipients are meaningful
const HAS_DATA_STATUSES = new Set(['sending', 'paused', 'sent', 'cancelled', 'failed']);

export function CampaignDetail({ campaign: initialCampaign, onBack }: CampaignDetailProps) {
  const {
    campaign, stats, recipients, recipientsTotal, recipientsPage, setRecipientsPage,
    statusFilter, setStatusFilter, recipientSearch, setRecipientSearch,
    loading, refetch,
  } = useCampaignDetail(initialCampaign.id, true);
  const [actionLoading, setActionLoading] = useState('');
  const [selectedLog, setSelectedLog] = useState<CampaignRecipient | null>(null);
  const confirm = useConfirm();

  const data = campaign ?? initialCampaign;
  const hasData = HAS_DATA_STATUSES.has(data.status);
  const showStats = hasData && stats && (stats.total > 0 || data.status === 'sending');
  const showRecipients = hasData;

  const defaultTab = useMemo(() => {
    if (!stats) return '';
    if (data.status === 'failed' && stats.failed > 0) return 'failed';
    if (stats.total > 0 && stats.delivered === 0 && stats.failed === stats.total) return 'failed';
    return '';
  }, [data.status, stats]);

  const deliveryRate = stats && stats.total > 0
    ? Math.round((stats.delivered / stats.total) * 100)
    : 0;

  const handleAction = async (action: string) => {
    if (action === 'cancel') {
      const ok = await confirm({
        title: 'Cancel campaign?',
        description: 'This will stop the campaign and no further messages will be sent. This cannot be undone.',
        confirmLabel: 'Cancel Campaign',
        variant: 'destructive',
      });
      if (!ok) return;
    }
    setActionLoading(action);
    try {
      await api.post(`campaigns/${data.id}/${action}`, {});
      toast.success(sprintf(__('Campaign %sed.', 'wp-sms'), action));
      refetch();
    } catch {
      toast.error(sprintf(__('Failed to %s campaign.', 'wp-sms'), action));
    } finally {
      setActionLoading('');
    }
  };

  const recipientPages = Math.ceil(recipientsTotal / 50);
  const detailsDefaultOpen = !hasData;

  const actionButtons = (
    <>
      {data.status === 'sending' && (
        <Button variant="outline" size="sm" onClick={() => void handleAction('pause')} disabled={!!actionLoading}>
          <Pause className="me-1 h-3.5 w-3.5" />
          {__('Pause', 'wp-sms')}
        </Button>
      )}
      {data.status === 'paused' && (
        <Button variant="outline" size="sm" onClick={() => void handleAction('resume')} disabled={!!actionLoading}>
          <Play className="me-1 h-3.5 w-3.5" />
          {__('Resume', 'wp-sms')}
        </Button>
      )}
      {(data.status === 'sending' || data.status === 'scheduled') && (
        <Button variant="outline" size="sm" onClick={() => void handleAction('cancel')} disabled={!!actionLoading}>
          <Ban className="me-1 h-3.5 w-3.5" />
          {__('Cancel', 'wp-sms')}
        </Button>
      )}
    </>
  );

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
          {__('Back', 'wp-sms')}
        </Button>
      </div>

      <PageHeader
        title={data.name}
        metadata={
          <>
            <CampaignStatusBadge status={data.status} />
            {channelLabel(data.channel)}
          </>
        }
        actions={actionButtons}
      />

      {showStats ? (
        <StatsSection data={data} stats={stats} deliveryRate={deliveryRate} />
      ) : hasData && stats?.total === 0 && data.status !== 'sending' ? (
        <NoMessagesSentNotice status={data.status} />
      ) : null}

      <CampaignDetailsSection data={data} defaultOpen={detailsDefaultOpen} />

      <TimelineSection data={data} />

      {showRecipients && (
        <RecipientsSection
          data={data}
          stats={stats}
          recipients={recipients}
          recipientsTotal={recipientsTotal}
          recipientsPage={recipientsPage}
          recipientPages={recipientPages}
          setRecipientsPage={setRecipientsPage}
          statusFilter={statusFilter}
          setStatusFilter={setStatusFilter}
          setRecipientSearch={setRecipientSearch}
          defaultTab={defaultTab}
          loading={loading && recipients.length === 0}
          selectedLog={selectedLog}
          setSelectedLog={setSelectedLog}
        />
      )}

      <MessageLogDetailPanel log={selectedLog} onClose={() => setSelectedLog(null)} />
    </div>
  );
}

// ── Stats Section ────────────────────────────────────────────────

function StatsSection({ data, stats, deliveryRate }: {
  data: Campaign;
  stats: CampaignStats;
  deliveryRate: number;
}) {
  const isAllFailed = stats.total > 0 && stats.delivered === 0 && stats.failed === stats.total;
  const showCost = stats.total_cost > 0;
  const hasDeliveryTracking = stats.supports_delivery_receipt;

  const heroLabel = hasDeliveryTracking ? 'Delivery Rate' : 'Send Rate';
  const heroRate = hasDeliveryTracking
    ? deliveryRate
    : stats.total > 0
      ? Math.round(((stats.delivered + stats.sent) / stats.total) * 100)
      : 0;

  const sentSuccessCount = hasDeliveryTracking ? stats.sent : stats.delivered + stats.sent;

  return (
    <div className="space-y-3">
      <div className="animate-fade-up rounded-lg border bg-card p-5" style={{ animationDelay: '0ms' }}>
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-muted-foreground">{heroLabel}</p>
            <p className={cn(
              'text-4xl font-bold tabular-nums tracking-tight',
              isAllFailed ? 'text-destructive' : heroRate >= 90 ? 'text-emerald-600' : '',
            )}>
              {heroRate}%
            </p>
            {showCost && (
              <p className="mt-1 text-sm text-muted-foreground">
                Total cost: ${stats.total_cost.toFixed(4)}
              </p>
            )}
            {hasDeliveryTracking && deliveryRate === 0 && stats.sent > 0 && !isAllFailed && (
              <p className="mt-1 text-xs text-muted-foreground">
                {pluralize(stats.sent, 'message')} sent, awaiting delivery confirmation
              </p>
            )}
            {!hasDeliveryTracking && (
              <p className="mt-1 text-xs text-muted-foreground">
                {__('Delivery tracking not available for this gateway', 'wp-sms')}
              </p>
            )}
          </div>
          {data.status === 'sending' && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <span className="relative flex h-2.5 w-2.5">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75" />
                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500" />
              </span>
              Sending...
            </div>
          )}
        </div>

        {stats.total > 0 && (
          <SegmentedStatusBar stats={stats} hasDeliveryTracking={hasDeliveryTracking} />
        )}
      </div>

      {hasDeliveryTracking ? (
        <div className={cn('grid gap-3', showCost ? 'grid-cols-2 sm:grid-cols-5' : 'grid-cols-2 sm:grid-cols-4')}>
          <StatCard label="Delivered" value={stats.delivered.toLocaleString()} icon={CheckCircle} colorClass="text-emerald-500" delay={60} muted={stats.delivered === 0} />
          <StatCard label="Failed" value={stats.failed.toLocaleString()} icon={XCircle} colorClass="text-destructive" delay={120} muted={stats.failed === 0} />
          <StatCard
            label="Pending"
            value={(stats.sent + stats.queued).toLocaleString()}
            icon={Clock} colorClass="text-amber-500" delay={180}
            subtitle={data.quiet_hours ? `${stats.queued} queued (quiet hours active)` : undefined}
            muted={stats.sent + stats.queued === 0}
          />
          <StatCard label="Total" value={data.total_recipients.toLocaleString()} icon={Users} colorClass="text-blue-500" delay={240} subtitle={data.skipped_count > 0 ? `${data.skipped_count} skipped` : undefined} />
          {showCost && <StatCard label="Cost" value={`$${stats.total_cost.toFixed(4)}`} icon={DollarSign} colorClass="text-purple-500" delay={300} />}
        </div>
      ) : (
        <div className={cn('grid gap-3', showCost ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-2 sm:grid-cols-3')}>
          <StatCard label="Sent" value={sentSuccessCount.toLocaleString()} icon={CheckCircle} colorClass="text-emerald-500" delay={60} muted={sentSuccessCount === 0} />
          <StatCard label="Failed" value={stats.failed.toLocaleString()} icon={XCircle} colorClass="text-destructive" delay={120} muted={stats.failed === 0} />
          <StatCard label="Total" value={data.total_recipients.toLocaleString()} icon={Users} colorClass="text-blue-500" delay={180} subtitle={data.skipped_count > 0 ? `${data.skipped_count} skipped` : undefined} />
          {showCost && <StatCard label="Cost" value={`$${stats.total_cost.toFixed(4)}`} icon={DollarSign} colorClass="text-purple-500" delay={240} />}
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, icon: Icon, colorClass, subtitle, delay, muted }: {
  label: string;
  value: string;
  icon: React.ComponentType<{ className?: string }>;
  colorClass: string;
  subtitle?: string;
  delay: number;
  muted?: boolean;
}) {
  return (
    <div
      className={cn('animate-fade-up rounded-lg border bg-card p-4', muted && 'opacity-50')}
      style={{ animationDelay: `${delay}ms` }}
    >
      <div className="flex items-center gap-2">
        <Icon className={cn('h-4 w-4', colorClass)} />
        <span className="text-xs text-muted-foreground">{label}</span>
      </div>
      <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
      {subtitle && (
        <p className="mt-0.5 text-xs text-muted-foreground">{subtitle}</p>
      )}
    </div>
  );
}

function SegmentedStatusBar({ stats, hasDeliveryTracking }: { stats: CampaignStats; hasDeliveryTracking: boolean }) {
  const total = stats.total;
  if (total === 0) return null;

  const segments = hasDeliveryTracking
    ? [
        { key: 'delivered', count: stats.delivered, color: 'bg-emerald-500', label: 'Delivered' },
        { key: 'sent', count: stats.sent, color: 'bg-blue-500', label: 'Sent' },
        { key: 'queued', count: stats.queued, color: 'bg-gray-300 dark:bg-gray-600', label: 'Queued' },
        { key: 'failed', count: stats.failed, color: 'bg-destructive', label: 'Failed' },
      ].filter(s => s.count > 0)
    : [
        { key: 'sent', count: stats.delivered + stats.sent, color: 'bg-emerald-500', label: 'Sent' },
        { key: 'queued', count: stats.queued, color: 'bg-gray-300 dark:bg-gray-600', label: 'Queued' },
        { key: 'failed', count: stats.failed, color: 'bg-destructive', label: 'Failed' },
      ].filter(s => s.count > 0);

  return (
    <div className="mt-4">
      <div className="flex h-2.5 overflow-hidden rounded-md bg-muted">
        {segments.map((seg) => (
          <div
            key={seg.key}
            className={cn('transition-all duration-500', seg.color)}
            style={{ width: `${(seg.count / total) * 100}%` }}
            title={`${seg.label}: ${seg.count}`}
          />
        ))}
      </div>
      <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
        {segments.map((seg) => (
          <div key={seg.key} className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <span className={cn('h-2 w-2 rounded-full', seg.color)} />
            {seg.label} ({seg.count.toLocaleString()})
          </div>
        ))}
      </div>
    </div>
  );
}

function NoMessagesSentNotice({ status }: { status: string }) {
  const messages: Record<string, { title: string; description: string; icon: React.ComponentType<{ className?: string }> }> = {
    cancelled: {
      title: 'Campaign was cancelled',
      description: 'This campaign was cancelled before any messages were sent.',
      icon: Ban,
    },
    failed: {
      title: 'Campaign failed to start',
      description: 'An error occurred before any messages could be sent.',
      icon: AlertTriangle,
    },
  };
  const msg = messages[status] ?? {
    title: 'No messages sent',
    description: 'This campaign has not sent any messages yet.',
    icon: Info,
  };

  return (
    <div className="animate-fade-up rounded-lg border border-dashed p-6">
      <EmptyState icon={msg.icon} title={msg.title} description={msg.description} compact />
    </div>
  );
}

// ── Campaign Details Section ────────────────────────────────────

function CampaignDetailsSection({ data, defaultOpen }: { data: Campaign; defaultOpen: boolean }) {
  const audienceSummary = data.audience.sources
    .map(s => {
      if (s.type === 'tags') return `Tags (${s.tag_ids?.length ?? 0})`;
      if (s.type === 'wp_roles') return `Roles (${s.roles?.join(', ')})`;
      if (s.type === 'manual') return `Manual (${s.recipients?.length ?? 0})`;
      if (s.type === 'segment') return 'Segment';
      return s.type;
    })
    .join(', ') || 'None';

  const sectionDescription = [
    channelLabel(data.channel),
    data.gateway_id ? `via ${data.gateway_id}` : null,
    data.total_recipients > 0 ? pluralize(data.total_recipients, 'recipient') : null,
  ].filter(Boolean).join(' \u00b7 ');

  return (
    <PageSection
      icon={FileText}
      title={__('Campaign Details', 'wp-sms')}
      description={sectionDescription}
      collapsible
      defaultOpen={defaultOpen}
      className="animate-fade-up"
      style={{ animationDelay: '120ms' }}
    >
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
          <MetadataField label="Channel" value={channelLabel(data.channel)} />
          <MetadataField label="Gateway" value={data.gateway_id ?? 'Default'} />
          <MetadataField label="Audience" value={audienceSummary} />
          {data.send_at && (
            <MetadataField label="Scheduled" value={formatDateTime(data.send_at)} />
          )}
          <MetadataField label="Timezone" value={data.timezone} />
          {data.recurrence && (
            <MetadataField
              label="Recurrence"
              value={`Repeats ${data.recurrence.frequency}${data.recurrence.end_date ? ` until ${formatDateTime(data.recurrence.end_date)}` : ''}`}
              icon={Repeat}
            />
          )}
          {data.quiet_hours && (
            <MetadataField
              label="Quiet Hours"
              value={`${data.quiet_hours.start} \u2013 ${data.quiet_hours.end} (${data.quiet_hours.timezone})`}
              icon={Moon}
            />
          )}
          {data.parent_id && (
            <MetadataField label="Series" value="Part of recurring series" />
          )}
        </div>

        {(data.subject || data.body) && (
          <div>
            <p className="mb-1.5 text-xs font-medium text-muted-foreground">{__('Message', 'wp-sms')}</p>
            {data.subject && (
              <p className="mb-1 text-sm font-semibold">{data.subject}</p>
            )}
            {data.body && (
              <div className="rounded-md border p-3 text-sm whitespace-pre-wrap">
                {data.body}
              </div>
            )}
          </div>
        )}

        {data.media_url && (
          <div>
            <p className="mb-1.5 text-xs font-medium text-muted-foreground">{__('Media', 'wp-sms')}</p>
            <img
              src={data.media_url}
              alt="Campaign media"
              className="max-h-40 rounded-md border object-contain"
            />
          </div>
        )}
      </div>
    </PageSection>
  );
}

function MetadataField({ label, value, icon: Icon }: {
  label: string;
  value: string;
  icon?: React.ComponentType<{ className?: string }>;
}) {
  return (
    <div>
      <span className="text-xs text-muted-foreground">{label}</span>
      <p className="flex items-center gap-1.5 text-sm">
        {Icon && <Icon className="h-3.5 w-3.5 text-muted-foreground" />}
        {value}
      </p>
    </div>
  );
}

// ── Timeline Section ────────────────────────────────────────────

const TIMELINE_DOT_COLORS: Record<string, string> = {
  completed: 'bg-emerald-500',
  started: 'bg-blue-500',
  scheduled: 'bg-amber-500',
  cancelled: 'bg-destructive',
  paused: 'bg-amber-500',
  created: 'bg-gray-400',
};

function TimelineSection({ data }: { data: Campaign }) {
  const events: { label: string; time: string; type: string; detail?: string }[] = [];

  if (data.send_at && data.status === 'scheduled') {
    events.push({
      label: 'Scheduled for',
      time: data.send_at,
      type: 'scheduled',
      detail: formatFutureRelativeTime(data.send_at),
    });
  }

  if (data.started_at) {
    events.push({ label: 'Sending started', time: data.started_at, type: 'started' });
  }

  if (data.status === 'paused' && data.started_at) {
    events.push({ label: 'Paused', time: data.started_at, type: 'paused' });
  }

  if (data.cancelled_at) {
    const duration = data.started_at ? formatDuration(data.started_at, data.cancelled_at) : undefined;
    events.push({
      label: 'Cancelled',
      time: data.cancelled_at,
      type: 'cancelled',
      detail: duration ? `After ${duration}` : undefined,
    });
  }

  if (data.completed_at) {
    const duration = data.started_at ? formatDuration(data.started_at, data.completed_at) : undefined;
    events.push({
      label: 'Completed',
      time: data.completed_at,
      type: 'completed',
      detail: duration ? `Completed in ${duration}` : undefined,
    });
  }

  if (events.length === 0) {
    events.push({ label: 'Draft created', time: '', type: 'created' });
  }

  return (
    <PageSection
      icon={Clock}
      title={__('Timeline', 'wp-sms')}
      className="animate-fade-up"
      style={{ animationDelay: '180ms' }}
    >
      <div className="relative space-y-0">
        {events.map((event, i) => {
          const dotColor = TIMELINE_DOT_COLORS[event.type] ?? 'bg-gray-400';
          const isSending = data.status === 'sending' && event.type === 'started';
          const isLast = i === events.length - 1;

          return (
            <div key={`${event.type}-${i}`} className="relative flex gap-3 pb-4 last:pb-0">
              {!isLast && (
                <div className="absolute start-[5px] top-3 bottom-0 w-px bg-border" />
              )}
              <div className="relative mt-1.5 flex-shrink-0">
                {isSending ? (
                  <span className="relative flex h-2.5 w-2.5">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75" />
                    <span className={cn('relative inline-flex h-2.5 w-2.5 rounded-full', dotColor)} />
                  </span>
                ) : (
                  <span className={cn('block h-2.5 w-2.5 rounded-full', dotColor)} />
                )}
              </div>
              <div className="min-w-0">
                <p className="text-sm font-medium">{event.label}</p>
                {event.time && (
                  <p className="text-xs text-muted-foreground">
                    {formatDateTime(event.time)}
                    {' \u00b7 '}
                    {event.type === 'scheduled' ? formatFutureRelativeTime(event.time) : formatRelativeTime(event.time)}
                  </p>
                )}
                {event.detail && (
                  <p className="text-xs text-muted-foreground">{event.detail}</p>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </PageSection>
  );
}

// ── Recipients Section ──────────────────────────────────────────

function RecipientsSection({
  data, stats, recipients, recipientsTotal, recipientsPage, recipientPages,
  setRecipientsPage, statusFilter, setStatusFilter, setRecipientSearch,
  defaultTab, loading, selectedLog, setSelectedLog,
}: {
  data: Campaign;
  stats: CampaignStats | null;
  recipients: CampaignRecipient[];
  recipientsTotal: number;
  recipientsPage: number;
  recipientPages: number;
  setRecipientsPage: (page: number) => void;
  statusFilter: string;
  setStatusFilter: (status: string) => void;
  setRecipientSearch: (search: string) => void;
  defaultTab: string;
  loading: boolean;
  selectedLog: CampaignRecipient | null;
  setSelectedLog: (log: CampaignRecipient | null) => void;
}) {
  const [searchInput, setSearchInput] = useState('');
  const tabValue = statusFilter || defaultTab || 'all';

  const handleTabChange = (value: string) => {
    setStatusFilter(value === 'all' ? '' : value);
  };

  const tabs = [
    { value: 'all', label: 'All', count: stats?.total ?? 0 },
    { value: 'delivered', label: 'Delivered', count: stats?.delivered ?? 0 },
    { value: 'failed', label: 'Failed', count: stats?.failed ?? 0 },
    { value: 'sent', label: 'Sent', count: stats?.sent ?? 0 },
    { value: 'queued', label: 'Queued', count: stats?.queued ?? 0 },
  ];

  const emptyMessages: Record<string, { title: string; description: string }> = {
    all: { title: 'No recipients yet', description: 'Recipients will appear here once the campaign starts sending.' },
    delivered: { title: 'No delivered messages', description: 'Messages that are successfully delivered will appear here.' },
    failed: { title: 'No failed messages', description: 'Great news \u2014 no messages have failed.' },
    sent: { title: 'No sent messages', description: 'Messages that have been sent but not yet confirmed will appear here.' },
    queued: { title: 'No queued messages', description: 'All messages have been processed.' },
  };

  const empty = emptyMessages[tabValue] ?? emptyMessages.all;

  return (
    <PageSection
      icon={Users}
      title={__('Recipients', 'wp-sms')}
      description={`${recipientsTotal.toLocaleString()} ${statusFilter ? statusFilter : 'total'}`}
      className="animate-fade-up"
      style={{ animationDelay: '240ms' }}
    >
      <Tabs value={tabValue} onValueChange={handleTabChange}>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <TabsList variant="line">
            {tabs.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value}>
                {tab.label}
                {tab.count > 0 && (
                  <span className="ms-1 text-xs text-muted-foreground">({tab.count.toLocaleString()})</span>
                )}
              </TabsTrigger>
            ))}
          </TabsList>

          <form
            className="relative"
            onSubmit={(e) => { e.preventDefault(); setRecipientSearch(searchInput); }}
          >
            <Search className="absolute start-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder={__('Search recipient...', 'wp-sms')}
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              className="h-8 w-52 ps-8"
            />
          </form>
        </div>

        {/* Single TabsContent — filtering is server-side, all tabs render the same data */}
        <TabsContent value={tabValue} forceMount>
          <DataTable
            loading={loading}
            skeletonRows={5}
            isEmpty={recipients.length === 0}
            empty={
              <EmptyState
                icon={tabValue === 'failed' ? CheckCircle : Users}
                title={empty.title}
                description={empty.description}
                compact
              />
            }
            pagination={recipientPages > 1 ? {
              page: recipientsPage,
              totalPages: recipientPages,
              onPageChange: setRecipientsPage,
            } : undefined}
          >
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{__('Recipient', 'wp-sms')}</TableHead>
                  <TableHead>{__('Status', 'wp-sms')}</TableHead>
                  <TableHead>{__('Cost', 'wp-sms')}</TableHead>
                  <TableHead>{__('Sent', 'wp-sms')}</TableHead>
                  <TableHead>{__('Delivered', 'wp-sms')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recipients.map((log) => (
                  <TableRow
                    key={log.id}
                    className={cn(
                      'cursor-pointer even:bg-muted/30',
                      selectedLog?.id === log.id && 'bg-accent',
                    )}
                    onClick={() => setSelectedLog(log)}
                  >
                    <TableCell className="font-mono text-sm">{log.recipient}</TableCell>
                    <TableCell>
                      <StatusBadge status={log.status} />
                    </TableCell>
                    <TableCell className="text-sm">
                      {log.cost ? `$${parseFloat(log.cost).toFixed(4)}` : '\u2014'}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {log.sent_at ? formatDateTime(log.sent_at) : '\u2014'}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {log.delivered_at ? formatDateTime(log.delivered_at) : '\u2014'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </DataTable>
        </TabsContent>
      </Tabs>
    </PageSection>
  );
}
