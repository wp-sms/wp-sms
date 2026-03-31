import { __, sprintf } from '@wordpress/i18n';
import { Plus, ArrowRight, Clock, Megaphone } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { channelLabel, channelBadgeVariant } from '@/lib/channel';
import type { DashboardSummaryResponse, ActiveCampaignSummary } from '@/lib/api';

function CampaignRow({ campaign, index }: { campaign: ActiveCampaignSummary; index: number }) {
  const total = campaign.total_recipients || 1;
  const sent = campaign.sent_count + campaign.delivered_count;
  const progress = Math.round((sent / total) * 100);
  const isSending = campaign.status === 'sending';
  const isScheduled = campaign.status === 'scheduled';

  return (
    <div
      className="animate-fade-up rounded-lg border bg-card p-4"
      style={{ animationDelay: `${index * 60}ms` }}
    >
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          {isSending && (
            <span className="relative flex h-2 w-2 shrink-0">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
              <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
            </span>
          )}
          {isScheduled && <Clock className="size-3.5 shrink-0 text-muted-foreground" />}
          <p className="text-sm font-medium truncate">{campaign.name}</p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <Badge variant={channelBadgeVariant(campaign.channel)}>
            {channelLabel(campaign.channel)}
          </Badge>
          <Badge variant={isSending ? 'success' : isScheduled ? 'info' : 'warning'}>
            {campaign.status}
          </Badge>
        </div>
      </div>

      {!isScheduled && (
        <div className="mt-3">
          <div className="flex items-center justify-between text-xs text-muted-foreground mb-1">
            <span>{sprintf(__('%d%% complete', 'wp-sms'), progress)}</span>
            <span className="tabular-nums">{sent.toLocaleString()} / {total.toLocaleString()}</span>
          </div>
          <div className="flex h-2.5 overflow-hidden rounded-md bg-muted">
            <div
              className={cn(
                'h-full transition-all duration-500',
                isSending ? 'bg-emerald-500' : 'bg-amber-500',
              )}
              style={{ width: `${Math.min(progress, 100)}%` }}
            />
            {campaign.failed_count > 0 && (
              <div
                className="h-full bg-destructive transition-all duration-500"
                style={{ width: `${Math.min((campaign.failed_count / total) * 100, 100 - progress)}%` }}
              />
            )}
          </div>
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <div className="flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-emerald-500" />
              {sprintf(__('Delivered (%s)', 'wp-sms'), campaign.delivered_count.toLocaleString())}
            </div>
            <div className="flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-blue-500" />
              {sprintf(__('Sent (%s)', 'wp-sms'), campaign.sent_count.toLocaleString())}
            </div>
            {campaign.failed_count > 0 && (
              <div className="flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-destructive" />
                {sprintf(__('Failed (%s)', 'wp-sms'), campaign.failed_count.toLocaleString())}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function EmptyState({ onNavigate }: { onNavigate: (s: string) => void }) {
  return (
    <div className="flex flex-col items-center justify-center py-8 text-center">
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted mb-4">
        <Megaphone className="size-6 text-muted-foreground" />
      </div>
      <p className="text-sm font-medium">{__('Create your first campaign', 'wp-sms')}</p>
      <p className="text-xs text-muted-foreground mt-1">{__('Send SMS, email, or Telegram messages to your contacts.', 'wp-sms')}</p>
      <Button size="sm" className="mt-4" onClick={() => onNavigate('campaigns')}>
        <Plus className="size-3.5" />
        {__('New Campaign', 'wp-sms')}
      </Button>
    </div>
  );
}

interface CampaignsCardProps {
  data: DashboardSummaryResponse;
  onNavigate: (section: string) => void;
  fullWidth?: boolean;
}

export function CampaignsCard({ data, onNavigate, fullWidth }: CampaignsCardProps) {
  const { messaging } = data;
  const campaigns = messaging.top_active_campaigns;
  const hasAnyCampaigns = messaging.active_campaigns > 0 || messaging.completed_campaigns > 0;
  const extraCount = messaging.active_campaigns - campaigns.length;

  return (
    <Card className={cn('animate-fade-up', fullWidth && 'col-span-full')}>
      <CardHeader>
        <CardTitle className="text-base">{__('Active Campaigns', 'wp-sms')}</CardTitle>
      </CardHeader>
      <CardContent>
        {!hasAnyCampaigns ? (
          <EmptyState onNavigate={onNavigate} />
        ) : campaigns.length === 0 ? (
          <div className="py-6 text-center">
            <p className="text-sm text-muted-foreground">{__('All campaigns completed.', 'wp-sms')}</p>
            <p className="text-xs text-muted-foreground mt-1">
              {sprintf(__('%s completed in this period', 'wp-sms'), messaging.completed_campaigns.toLocaleString())}
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {campaigns.map((campaign, i) => (
              <CampaignRow key={campaign.id} campaign={campaign} index={i} />
            ))}
            {extraCount > 0 && (
              <button
                type="button"
                className="w-full text-center text-xs text-muted-foreground hover:text-foreground transition-colors py-1"
                onClick={() => onNavigate('campaigns')}
              >
                {sprintf(__('+ %d more campaigns', 'wp-sms'), extraCount)}
              </button>
            )}
          </div>
        )}
      </CardContent>
      {hasAnyCampaigns && (
        <CardFooter className="justify-between border-t pt-4">
          <Button variant="outline" size="sm" onClick={() => onNavigate('campaigns')}>
            <Plus className="size-3.5" />
            {__('New Campaign', 'wp-sms')}
          </Button>
          <Button variant="ghost" size="sm" onClick={() => onNavigate('campaigns')}>
            {__('View All', 'wp-sms')}
            <ArrowRight className="size-3.5" />
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}

export function CampaignsCardSkeleton() {
  return (
    <Card>
      <CardHeader>
        <Skeleton className="h-5 w-36" />
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          <Skeleton className="h-24 w-full rounded-lg" />
          <Skeleton className="h-24 w-full rounded-lg" />
        </div>
      </CardContent>
    </Card>
  );
}
