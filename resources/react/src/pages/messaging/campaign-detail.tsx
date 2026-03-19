import { useCampaignDetail } from '@/hooks/use-campaign-detail';
import type { Campaign, CampaignRecipient } from '@/lib/api';
import { MessageLogDetailDrawer } from '@/components/messaging/message-log-detail-drawer';
import { CampaignStatusBadge } from '@/components/campaigns/campaign-status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Progress } from '@/components/ui/progress';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { PageNumbers } from '@/components/ui/pagination';
import {
  ArrowLeft, CheckCircle, XCircle, Clock, Users,
  Pause, Play, Ban, DollarSign, SkipForward,
} from 'lucide-react';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-provider';

interface CampaignDetailProps {
  campaign: Campaign;
  onBack: () => void;
}

export function CampaignDetail({ campaign: initialCampaign, onBack }: CampaignDetailProps) {
  const {
    campaign, stats, recipients, recipientsTotal, recipientsPage, setRecipientsPage, loading,
    refetch,
  } = useCampaignDetail(initialCampaign.id, true);
  const [actionLoading, setActionLoading] = useState('');
  const [selectedLog, setSelectedLog] = useState<CampaignRecipient | null>(null);
  const confirm = useConfirm();

  const data = campaign ?? initialCampaign;

  const deliveryRate = stats && stats.total > 0
    ? Math.round((stats.delivered / stats.total) * 100)
    : 0;

  const pendingCount = stats
    ? stats.total - stats.delivered - stats.failed
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
      toast.success(`Campaign ${action}ed.`);
      refetch();
    } catch {
      toast.error(`Failed to ${action} campaign.`);
    } finally {
      setActionLoading('');
    }
  };

  const recipientPages = Math.ceil(recipientsTotal / 50);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" onClick={onBack}>
            <ArrowLeft className="mr-1 h-4 w-4" />
            Back
          </Button>
          <h2 className="text-lg font-semibold">{data.name}</h2>
          <CampaignStatusBadge status={data.status} />
        </div>
        <div className="flex gap-2">
          {data.status === 'sending' && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => void handleAction('pause')}
              disabled={!!actionLoading}
            >
              <Pause className="mr-1 h-3.5 w-3.5" />
              Pause
            </Button>
          )}
          {data.status === 'paused' && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => void handleAction('resume')}
              disabled={!!actionLoading}
            >
              <Play className="mr-1 h-3.5 w-3.5" />
              Resume
            </Button>
          )}
          {(data.status === 'sending' || data.status === 'scheduled') && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => void handleAction('cancel')}
              disabled={!!actionLoading}
            >
              <Ban className="mr-1 h-3.5 w-3.5" />
              Cancel
            </Button>
          )}
        </div>
      </div>

      {/* Stats cards */}
      {loading && !stats ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-20" />
          ))}
        </div>
      ) : stats ? (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <StatCard
              label="Total"
              value={data.total_recipients.toLocaleString()}
              icon={Users}
              iconColor="text-blue-500"
            />
            <StatCard
              label="Delivered"
              value={stats.delivered.toLocaleString()}
              icon={CheckCircle}
              iconColor="text-emerald-500"
            />
            <StatCard
              label="Failed"
              value={stats.failed.toLocaleString()}
              icon={XCircle}
              iconColor="text-red-500"
            />
            <StatCard
              label="Pending"
              value={pendingCount.toLocaleString()}
              icon={Clock}
              iconColor="text-amber-500"
            />
            <StatCard
              label="Skipped"
              value={data.skipped_count.toLocaleString()}
              icon={SkipForward}
              iconColor="text-gray-500"
            />
            <StatCard
              label="Cost"
              value={stats.total_cost > 0 ? `$${stats.total_cost.toFixed(4)}` : 'N/A'}
              icon={DollarSign}
              iconColor="text-purple-500"
            />
          </div>

          {/* Delivery progress */}
          {stats.total > 0 && (
            <Card>
              <CardContent className="pt-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium">Delivery Rate</span>
                  <span className="text-sm text-muted-foreground">{deliveryRate}%</span>
                </div>
                <Progress value={deliveryRate} className="h-2" />
                {data.status === 'sent' && pendingCount > 0 && (
                  <p className="text-xs text-muted-foreground mt-2">
                    Sending complete — {pendingCount} messages still queued for delivery
                  </p>
                )}
              </CardContent>
            </Card>
          )}
        </>
      ) : null}

      {/* Event timeline */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-sm">Timeline</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2 text-sm">
            {data.started_at && (
              <TimelineItem label="Sending started" time={data.started_at} />
            )}
            {data.send_at && data.status === 'scheduled' && (
              <TimelineItem label="Scheduled for" time={data.send_at} />
            )}
            {data.completed_at && (
              <TimelineItem label="Completed" time={data.completed_at} />
            )}
            {data.cancelled_at && (
              <TimelineItem label="Cancelled" time={data.cancelled_at} />
            )}
          </div>
        </CardContent>
      </Card>

      {/* Recipients */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm">Recipients</CardTitle>
            <span className="text-xs text-muted-foreground">{recipientsTotal} total</span>
          </div>
        </CardHeader>
        <CardContent>
          {recipients.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">No recipients yet.</p>
          ) : (
            <>
              <div className="rounded-lg border border-border/50 overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Recipient</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Cost</TableHead>
                      <TableHead>Sent</TableHead>
                      <TableHead>Delivered</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {recipients.map((log) => (
                      <TableRow
                        key={log.id}
                        className={`cursor-pointer even:bg-muted/30 ${selectedLog?.id === log.id ? 'bg-accent' : ''}`}
                        onClick={() => setSelectedLog(log)}
                      >
                        <TableCell className="font-mono text-sm">{log.recipient}</TableCell>
                        <TableCell>
                          <RecipientStatusBadge status={log.status} />
                        </TableCell>
                        <TableCell className="text-sm">
                          {log.cost ? `$${parseFloat(log.cost).toFixed(4)}` : '\u2014'}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {log.sent_at ? new Date(log.sent_at).toLocaleString() : '\u2014'}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {log.delivered_at ? new Date(log.delivered_at).toLocaleString() : '\u2014'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <PageNumbers page={recipientsPage} totalPages={recipientPages} onPageChange={setRecipientsPage} />
            </>
          )}
        </CardContent>
      </Card>

      <MessageLogDetailDrawer log={selectedLog} onClose={() => setSelectedLog(null)} />
    </div>
  );
}

function StatCard({
  label,
  value,
  icon: Icon,
  iconColor,
}: {
  label: string;
  value: string;
  icon: React.ComponentType<{ className?: string }>;
  iconColor: string;
}) {
  return (
    <Card>
      <CardContent className="pt-4">
        <div className="flex items-center gap-2">
          <Icon className={`h-4 w-4 ${iconColor}`} />
          <span className="text-xs text-muted-foreground">{label}</span>
        </div>
        <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
      </CardContent>
    </Card>
  );
}

function TimelineItem({ label, time }: { label: string; time: string }) {
  return (
    <div className="flex items-center gap-3">
      <div className="h-2 w-2 rounded-full bg-primary" />
      <span className="font-medium">{label}</span>
      <span className="text-muted-foreground">{new Date(time).toLocaleString()}</span>
    </div>
  );
}

function RecipientStatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    delivered: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    sent:      'border-blue-200 bg-blue-50 text-blue-700',
    queued:    'border-gray-200 bg-gray-50 text-gray-600',
    failed:    'border-red-200 bg-red-50 text-red-700',
  };

  return (
    <Badge variant="outline" className={styles[status] ?? ''}>
      {status}
    </Badge>
  );
}
