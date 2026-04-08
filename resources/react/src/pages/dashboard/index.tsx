import { __ } from '@wordpress/i18n';
import { LayoutDashboard, RefreshCw, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useDashboard } from './use-dashboard';
import { StatCards, StatCardsSkeleton } from './stat-cards';
import { SystemStatusBanner } from './system-status-banner';
import { CampaignsCard, CampaignsCardSkeleton } from './campaigns-card';
import { ChannelsCard, ChannelsCardSkeleton } from './channels-card';
import { PlatformStatusCard, PlatformStatusCardSkeleton } from './platform-status-card';
import { SetupChecklist } from '@/components/onboarding/setup-checklist';
import { ContinueSetupCard } from '@/components/onboarding/continue-setup-card';
import { cn } from '@/lib/utils';

interface DashboardPageProps {
  onNavigate: (section: string) => void;
}

export function DashboardPage({ onNavigate }: DashboardPageProps) {
  const { data, loading, error, refetch } = useDashboard();

  const showChannels = !data || data.feature_state.gateways_configured;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <LayoutDashboard className="size-5 text-muted-foreground" />
          <h1 className="text-2xl font-bold tracking-tight">{__('Dashboard', 'wp-sms')}</h1>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={refetch}
          disabled={loading}
        >
          <RefreshCw className={cn('size-3.5', loading && 'animate-spin')} />
          {__('Refresh', 'wp-sms')}
        </Button>
      </div>

      {/* Resume-wizard prompt (shows during pending / in_progress) */}
      <ContinueSetupCard />

      {/* Setup Checklist (shows after wizard is completed / skipped) */}
      <SetupChecklist onNavigate={onNavigate} />

      {/* Error */}
      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* System Status Banner */}
      {data && <SystemStatusBanner health={data.system_health} onNavigate={onNavigate} />}

      {/* KPI Stat Cards */}
      {loading && !data ? <StatCardsSkeleton /> : data && <StatCards data={data} onNavigate={onNavigate} />}

      {/* Campaigns + Channels Row */}
      {loading && !data ? (
        <div className={cn('grid gap-6', showChannels ? 'lg:grid-cols-2' : '')}>
          <CampaignsCardSkeleton />
          {showChannels && <ChannelsCardSkeleton />}
        </div>
      ) : data && (
        showChannels ? (
          <div className="grid gap-6 lg:grid-cols-2">
            <CampaignsCard data={data} onNavigate={onNavigate} />
            <ChannelsCard data={data} onNavigate={onNavigate} />
          </div>
        ) : (
          <CampaignsCard data={data} onNavigate={onNavigate} fullWidth />
        )
      )}

      {/* Platform Status */}
      {loading && !data ? <PlatformStatusCardSkeleton /> : data && <PlatformStatusCard data={data} onNavigate={onNavigate} />}
    </div>
  );
}
