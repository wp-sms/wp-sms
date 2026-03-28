import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SystemHealthResponse } from '@/lib/api';

const CHANNEL_VARIANT = {
  sms: 'info',
  email: 'purple',
  whatsapp: 'success',
} as const;

interface ActiveCampaignsCardProps {
  data: SystemHealthResponse['active_campaigns'];
}

export function ActiveCampaignsCard({ data }: ActiveCampaignsCardProps) {
  if (data.length === 0) return null;

  return (
    <div className="space-y-3">
      {data.map((campaign, i) => {
        const total    = campaign.total_recipients || 1;
        const sent     = campaign.sent_count + campaign.delivered_count;
        const progress = Math.round((sent / total) * 100);
        const isSending = campaign.status === 'sending';

        return (
          <div
            key={campaign.id}
            className="animate-fade-up rounded-lg border bg-card p-4"
            style={{ animationDelay: `${i * 60}ms` }}
          >
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2 min-w-0">
                {isSending && (
                  <span className="relative flex h-2 w-2 shrink-0">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                    <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                  </span>
                )}
                <p className="text-sm font-medium truncate">{campaign.name}</p>
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <Badge variant={CHANNEL_VARIANT[campaign.channel as keyof typeof CHANNEL_VARIANT] ?? 'neutral'}>
                  {campaign.channel}
                </Badge>
                <Badge variant={isSending ? 'success' : 'warning'}>
                  {campaign.status}
                </Badge>
              </div>
            </div>

            <div className="mt-3">
              <div className="flex items-center justify-between text-xs text-muted-foreground mb-1">
                <span>{progress}% complete</span>
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
            </div>

            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <div className="flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-emerald-500" />
                Delivered ({campaign.delivered_count.toLocaleString()})
              </div>
              <div className="flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-blue-500" />
                Sent ({campaign.sent_count.toLocaleString()})
              </div>
              {campaign.failed_count > 0 && (
                <div className="flex items-center gap-1.5">
                  <span className="h-2 w-2 rounded-full bg-destructive" />
                  Failed ({campaign.failed_count.toLocaleString()})
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
