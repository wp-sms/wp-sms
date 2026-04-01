import { __ } from '@wordpress/i18n';
import { TrendingUp, TrendingDown } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { DashboardSummaryResponse } from '@/lib/api';

function formatCompact(n: number): { display: string; full: string | null } {
  if (n < 10_000) {
    return { display: n.toLocaleString(), full: null };
  }
  if (n >= 1_000_000) {
    return { display: `${(n / 1_000_000).toFixed(1)}M`, full: n.toLocaleString() };
  }
  return { display: `${(n / 1_000).toFixed(1)}K`, full: n.toLocaleString() };
}

interface StatCardConfig {
  key: string;
  label: string;
  getValue: (d: DashboardSummaryResponse) => { display: string; full: string | null };
  getDelta: (d: DashboardSummaryResponse) => number | null;
  getInsight?: (d: DashboardSummaryResponse) => { text: string; status: 'success' | 'warning' | 'destructive' } | null;
  getEmpty?: (d: DashboardSummaryResponse) => string | null;
  navigateTo?: string;
}

const messagesSentCard: StatCardConfig = {
  key: 'messages_sent',
  label: __('Messages Sent', 'wp-sms'),
  getValue: (d) => formatCompact(d.messaging.messages_sent),
  getDelta: (d) => d.messaging.messages_sent_delta,
  getEmpty: (d) => !d.feature_state.gateways_configured
    ? __('Configure a gateway to start sending', 'wp-sms')
    : d.messaging.messages_sent === 0 ? __('Send your first campaign', 'wp-sms') : null,
  navigateTo: 'campaigns',
};

const successRateCard: StatCardConfig = {
  key: 'success_rate',
  label: '', // set dynamically based on delivery tracking
  getValue: (d) => d.messaging.success_rate !== null
    ? { display: `${d.messaging.success_rate}%`, full: null }
    : { display: '\u2014', full: null },
  getDelta: () => null,
  getInsight: (d) => d.messaging.success_rate_insight && d.messaging.success_rate_status
    ? { text: d.messaging.success_rate_insight, status: d.messaging.success_rate_status }
    : null,
  getEmpty: (d) => d.messaging.success_rate === null ? __('No data yet', 'wp-sms') : null,
};

const activeCampaignsCard: StatCardConfig = {
  key: 'active_campaigns',
  label: __('Active Campaigns', 'wp-sms'),
  getValue: (d) => ({ display: d.messaging.active_campaigns.toString(), full: null }),
  getDelta: () => null,
  getEmpty: (d) => d.messaging.active_campaigns === 0 && d.messaging.completed_campaigns === 0
    ? __('No campaigns yet', 'wp-sms')
    : d.messaging.active_campaigns === 0 ? __('All completed', 'wp-sms') : null,
  navigateTo: 'campaigns',
};

const loginSuccessCard: StatCardConfig = {
  key: 'login_success_rate',
  label: __('Login Success Rate', 'wp-sms'),
  getValue: (d) => d.auth.login_success_rate != null
    ? { display: `${d.auth.login_success_rate}%`, full: null }
    : { display: '\u2014', full: null },
  getDelta: () => null,
  getInsight: (d) => d.auth.login_success_status
    ? { text: d.auth.login_success_rate != null ? `${d.auth.total_logins ?? 0} ${__('total logins', 'wp-sms')}` : __('No logins yet', 'wp-sms'), status: d.auth.login_success_status }
    : null,
};

const messagesFailedCard: StatCardConfig = {
  key: 'messages_failed',
  label: __('Messages Failed', 'wp-sms'),
  getValue: (d) => formatCompact(d.messaging.messages_failed),
  getDelta: () => null,
  getInsight: (d) => d.messaging.messages_failed > 0
    ? { text: __('Review message logs', 'wp-sms'), status: d.messaging.messages_failed > 50 ? 'destructive' : 'warning' }
    : null,
  navigateTo: 'monitoring/message-logs',
};

const registrationsCard: StatCardConfig = {
  key: 'registrations',
  label: __('Registrations', 'wp-sms'),
  getValue: (d) => formatCompact(d.auth.registrations ?? 0),
  getDelta: () => null,
};

const totalContactsCard: StatCardConfig = {
  key: 'total_contacts',
  label: __('Total Contacts', 'wp-sms'),
  getValue: (d) => formatCompact(d.contacts.total),
  getDelta: () => null,
  navigateTo: 'audience/contacts',
};

const failedLoginsCard: StatCardConfig = {
  key: 'failed_logins',
  label: __('Failed Logins', 'wp-sms'),
  getValue: (d) => formatCompact(d.auth.failed_logins ?? 0),
  getDelta: () => null,
  getInsight: (d) => (d.auth.failed_logins ?? 0) > 0
    ? { text: __('Review security logs', 'wp-sms'), status: 'warning' }
    : null,
};

function getCardsForState(data: DashboardSummaryResponse): StatCardConfig[] {
  const { feature_state } = data;

  // Set the rate card label based on delivery tracking availability
  const rateCard: StatCardConfig = {
    ...successRateCard,
    label: feature_state.delivery_tracking_available
      ? __('Delivery Rate', 'wp-sms')
      : __('Send Rate', 'wp-sms'),
  };

  // Hybrid: messaging + auth
  if (feature_state.auth_enabled && feature_state.gateways_configured) {
    return [messagesSentCard, rateCard, activeCampaignsCard, loginSuccessCard];
  }

  // Messaging only
  if (!feature_state.auth_enabled) {
    return [messagesSentCard, rateCard, activeCampaignsCard, messagesFailedCard];
  }

  // Auth only (no gateways)
  return [loginSuccessCard, registrationsCard, totalContactsCard, failedLoginsCard];
}

function DeltaIndicator({ delta }: { delta: number }) {
  if (delta === 0) return null;

  const isPositive = delta > 0;

  return (
    <span className={cn(
      'inline-flex items-center gap-0.5 text-xs font-medium',
      isPositive ? 'text-success' : 'text-destructive',
    )}>
      {isPositive ? <TrendingUp className="size-3" /> : <TrendingDown className="size-3" />}
      {isPositive ? '+' : ''}{delta}%
    </span>
  );
}

interface StatCardsProps {
  data: DashboardSummaryResponse;
  onNavigate: (section: string) => void;
}

export function StatCards({ data, onNavigate }: StatCardsProps) {
  const cards = getCardsForState(data);

  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map(({ key, label, getValue, getDelta, getInsight, getEmpty, navigateTo }, i) => {
        const value = getValue(data);
        const delta = getDelta(data);
        const insight = getInsight?.(data);
        const emptyMsg = getEmpty?.(data);

        const content = (
          <CardContent className="pt-6">
            <div className="space-y-1">
              <p className="text-3xl font-bold tracking-tight tabular-nums">{value.display}</p>
              <p className="text-sm text-muted-foreground">{label}</p>
              {insight && (
                <Badge variant={insight.status} className="text-[10px]">{insight.text}</Badge>
              )}
              {delta !== null && <DeltaIndicator delta={delta} />}
              {emptyMsg && !insight && <p className="text-xs text-muted-foreground">{emptyMsg}</p>}
            </div>
          </CardContent>
        );

        const card = (
          <Card
            key={key}
            className={cn(
              'animate-fade-up',
              navigateTo && 'cursor-pointer transition-shadow hover:shadow-md',
            )}
            style={{ animationDelay: `${i * 60}ms` }}
            onClick={navigateTo ? () => onNavigate(navigateTo) : undefined}
          >
            {content}
          </Card>
        );

        if (value.full) {
          return (
            <Tooltip key={key}>
              <TooltipTrigger asChild>{card}</TooltipTrigger>
              <TooltipContent>{value.full}</TooltipContent>
            </Tooltip>
          );
        }

        return card;
      })}
    </div>
  );
}

export function StatCardsSkeleton() {
  return (
    <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }, (_, i) => (
        <Card key={i}>
          <CardContent className="pt-6">
            <div className="space-y-2">
              <Skeleton className="h-9 w-24" />
              <Skeleton className="h-4 w-32" />
              <Skeleton className="h-3 w-20" />
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
