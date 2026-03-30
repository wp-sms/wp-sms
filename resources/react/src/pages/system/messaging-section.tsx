import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Wallet } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { api, type HealthCheck, type GatewayCreditsResponse } from '@/lib/api';
import { cn, fmtNumber } from '@/lib/utils';
import { HealthCheckRow } from './health-check-row';
import { toast } from 'sonner';

interface MessagingSectionProps {
  checks: HealthCheck[];
}

export function MessagingSection({ checks }: MessagingSectionProps) {
  const [credits, setCredits] = useState<GatewayCreditsResponse | null>(null);
  const [creditLoading, setCreditLoading] = useState(false);

  // Extract delivery stat cards from the 24h check details
  const deliveryCheck = checks.find(c => c.id === 'msg_delivery_rate_24h');
  const channelStats = deliveryCheck?.details?.channels as Record<string, { total: number; success: number; failed: number; bounced: number; rate: number }> | undefined;

  const loadCredits = async () => {
    setCreditLoading(true);
    try {
      const res = await api.get<{ success: boolean; data: GatewayCreditsResponse }>('system/health/gateway-credits');
      setCredits(res.data);
    } catch {
      toast.error(__('Failed to load gateway credits.', 'wp-sms'));
    } finally {
      setCreditLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Delivery stat cards */}
      {channelStats && Object.keys(channelStats).length > 0 && (
        <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
          {Object.entries(channelStats).map(([channel, stats], i) => (
            <div
              key={channel}
              className={cn(
                'animate-fade-up rounded-lg border bg-card p-4',
                stats.total === 0 && 'opacity-50',
              )}
              style={{ animationDelay: `${i * 40}ms` }}
            >
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{channel}</span>
                <Badge variant={stats.rate >= 90 ? 'success' : stats.rate >= 70 ? 'warning' : 'destructive'} className="text-xs">
                  {stats.rate}%
                </Badge>
              </div>
              <p className="mt-1 text-xl font-semibold tabular-nums">{fmtNumber(stats.total)}</p>
              <p className="text-xs text-muted-foreground">
                {sprintf(__('%s sent', 'wp-sms'), fmtNumber(stats.success))}
                {stats.failed > 0 && ` · ${sprintf(__('%s failed', 'wp-sms'), fmtNumber(stats.failed))}`}
              </p>
            </div>
          ))}
        </div>
      )}

      {/* Check rows */}
      <div className="space-y-1.5">
        {checks.map((check, i) => (
          <HealthCheckRow
            key={check.id}
            check={check}
            index={i}
            onLazyLoad={check.id === 'msg_gateway_credit' ? loadCredits : undefined}
            lazyLoading={check.id === 'msg_gateway_credit' ? creditLoading : false}
          />
        ))}
      </div>

      {/* Gateway credits result */}
      {credits && (
        <div className="space-y-2 rounded-lg border p-3">
          <div className="flex items-center gap-2 text-sm font-medium">
            <Wallet className="h-4 w-4 text-muted-foreground" />
            {__('Gateway Balances', 'wp-sms')}
          </div>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {Object.values(credits).map(gw => (
              <div key={gw.gateway_id} className="flex items-center justify-between rounded-md border px-3 py-2">
                <span className="text-sm">{gw.name}</span>
                {gw.error ? (
                  <Badge variant="destructive">{__('Error', 'wp-sms')}</Badge>
                ) : gw.credit !== null ? (
                  <span className="text-sm font-semibold tabular-nums">{gw.credit}</span>
                ) : (
                  <span className="text-xs text-muted-foreground">{__('N/A', 'wp-sms')}</span>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
