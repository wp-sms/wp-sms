import { __ } from '@wordpress/i18n';
import { ArrowRight, Mail } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { channelLabel, channelBadgeVariant } from '@/lib/channel';
import type { DashboardSummaryResponse, ChannelBreakdown } from '@/lib/api';

function rateStatus(rate: number | null): 'success' | 'warning' | 'destructive' | null {
  if (rate === null) return null;
  if (rate >= 85) return 'success';
  if (rate >= 70) return 'warning';
  return 'destructive';
}

const STATUS_DOT: Record<string, string> = {
  success: 'bg-success',
  warning: 'bg-warning',
  destructive: 'bg-destructive',
};

function ChannelRow({ channel, showCost }: { channel: ChannelBreakdown; showCost: boolean }) {
  const status = rateStatus(channel.success_rate);

  return (
    <div className="flex items-center gap-3 py-2.5">
      <Badge variant={channelBadgeVariant(channel.channel)} className="w-20 justify-center text-[10px]">
        {channelLabel(channel.channel)}
      </Badge>
      <div className="flex flex-1 items-center justify-between gap-2 text-sm">
        <span className="tabular-nums text-muted-foreground">
          {channel.successful.toLocaleString()} / {channel.sent.toLocaleString()}
        </span>
        <div className="flex items-center gap-1.5">
          {status && <span className={cn('h-1.5 w-1.5 rounded-full', STATUS_DOT[status])} />}
          <span className="tabular-nums font-medium">
            {channel.success_rate !== null ? `${channel.success_rate}%` : '\u2014'}
          </span>
        </div>
        {showCost && (
          <span className="tabular-nums text-muted-foreground w-16 text-end">
            ${channel.cost.toFixed(2)}
          </span>
        )}
      </div>
    </div>
  );
}

interface ChannelsCardProps {
  data: DashboardSummaryResponse;
  onNavigate: (section: string) => void;
}

export function ChannelsCard({ data, onNavigate }: ChannelsCardProps) {
  const { channels, feature_state } = data;
  const showCost = channels.some(c => c.cost > 0);
  const successLabel = feature_state.delivery_tracking_available
    ? __('Delivered / Sent', 'wp-sms')
    : __('Sent / Total', 'wp-sms');

  return (
    <Card className="animate-fade-up">
      <CardHeader>
        <CardTitle className="text-base">{__('Channels', 'wp-sms')}</CardTitle>
      </CardHeader>
      <CardContent>
        {channels.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted mb-3">
              <Mail className="size-5 text-muted-foreground" />
            </div>
            <p className="text-sm text-muted-foreground">{__('No messages sent in this period.', 'wp-sms')}</p>
          </div>
        ) : (
          <>
            {/* Header */}
            <div className="flex items-center gap-3 pb-1 text-xs text-muted-foreground">
              <span className="w-20">{__('Channel', 'wp-sms')}</span>
              <div className="flex flex-1 items-center justify-between gap-2">
                <span>{successLabel}</span>
                <span>{__('Rate', 'wp-sms')}</span>
                {showCost && <span className="w-16 text-end">{__('Cost', 'wp-sms')}</span>}
              </div>
            </div>
            <div className="divide-y divide-border">
              {channels.map((ch) => (
                <ChannelRow key={ch.channel} channel={ch} showCost={showCost} />
              ))}
            </div>
          </>
        )}
      </CardContent>
      {channels.length > 0 && (
        <CardFooter className="border-t pt-4">
          <Button variant="ghost" size="sm" className="ms-auto" onClick={() => onNavigate('monitoring/message-logs')}>
            {__('View Message Logs', 'wp-sms')}
            <ArrowRight className="size-3.5" />
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}

export function ChannelsCardSkeleton() {
  return (
    <Card>
      <CardHeader>
        <Skeleton className="h-5 w-24" />
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          <Skeleton className="h-5 w-full" />
          <Skeleton className="h-5 w-full" />
          <Skeleton className="h-5 w-full" />
        </div>
      </CardContent>
    </Card>
  );
}
