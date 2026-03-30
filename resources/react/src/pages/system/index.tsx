import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, type ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { PageHeader } from '@/components/layout/page-header';
import { PageSection } from '@/components/ui/page-section';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useSystemHealth } from '@/hooks/use-system-health';
import { usePolling } from '@/hooks/use-polling';
import { HealthSummary } from './health-summary';
import { MessagingSection } from './messaging-section';
import { AuthSection } from './auth-section';
import { BackgroundSection } from './background-section';
import { ConfigSection } from './config-section';
import { DataSecuritySection } from './data-security-section';
import { Activity, AlertCircle, RefreshCw, Send, ShieldCheck, Cog, Settings, Database } from 'lucide-react';
import { formatRelativeTime } from '@/lib/format';
import { toast } from 'sonner';
import type { SectionStatus } from '@/lib/api';

interface SystemHealthProps {
  embedded?: boolean;
  setHeaderMeta?: (node: ReactNode) => void;
  setHeaderActions?: (node: ReactNode) => void;
}

const SECTION_CONFIG = [
  { key: 'messaging', icon: Send, title: () => __('Messaging Delivery', 'wp-sms'), storageKey: 'health-messaging' },
  { key: 'auth', icon: ShieldCheck, title: () => __('Authentication', 'wp-sms'), storageKey: 'health-auth' },
  { key: 'background', icon: Cog, title: () => __('Background Processing', 'wp-sms'), storageKey: 'health-background' },
  { key: 'configuration', icon: Settings, title: () => __('Configuration', 'wp-sms'), storageKey: 'health-config' },
  { key: 'data_security', icon: Database, title: () => __('Data & Security', 'wp-sms'), storageKey: 'health-data-security' },
] as const;

const STATUS_BADGE_VARIANT: Record<SectionStatus, 'success' | 'warning' | 'destructive'> = {
  healthy: 'success',
  warning: 'warning',
  critical: 'destructive',
};

const STATUS_BADGE_LABEL: Record<SectionStatus, string> = {
  healthy: __('All OK', 'wp-sms'),
  warning: __('Warning', 'wp-sms'),
  critical: __('Critical', 'wp-sms'),
};

export function SystemHealth({ embedded, setHeaderMeta, setHeaderActions }: SystemHealthProps) {
  const { data, loading, error, refetch } = useSystemHealth();
  const prevLevelRef = useRef<string | undefined>();

  usePolling(refetch, 30_000, !loading);

  // Toast on status change
  useEffect(() => {
    if (!data) return;
    const currentLevel = data.overall_status.level;
    if (prevLevelRef.current !== undefined && prevLevelRef.current !== currentLevel) {
      const label = currentLevel === 'healthy'
        ? __('All systems operational', 'wp-sms')
        : currentLevel === 'critical'
          ? __('Critical issues detected', 'wp-sms')
          : __('Issues need attention', 'wp-sms');
      toast.info(sprintf(__('System status changed: %s', 'wp-sms'), label));
    }
    prevLevelRef.current = currentLevel;
  }, [data?.overall_status.level]);

  const refreshButton = (
    <Button variant="outline" size="sm" onClick={refetch} disabled={loading}>
      <RefreshCw className={`me-1.5 h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
      {__('Refresh', 'wp-sms')}
    </Button>
  );

  const timestamp = data && (
    <span className="text-xs tabular-nums text-muted-foreground">
      {__('Updated', 'wp-sms')} {formatRelativeTime(data.generated_at)}
    </span>
  );

  useEffect(() => {
    setHeaderMeta?.(timestamp ?? null);
    return () => setHeaderMeta?.(null);
  }, [data?.generated_at, setHeaderMeta]);

  useEffect(() => {
    setHeaderActions?.(refreshButton);
    return () => setHeaderActions?.(null);
  }, [loading, setHeaderActions]);

  return (
    <div className="space-y-4">
      {!embedded && (
        <PageHeader icon={Activity} title={__('System Health', 'wp-sms')} metadata={timestamp} actions={refreshButton} />
      )}
      {embedded && !setHeaderMeta && (
        <div className="flex items-center justify-between">
          {timestamp}
          {refreshButton}
        </div>
      )}

      {loading && !data && (
        <div className="space-y-4">
          <Skeleton className="h-16 w-full rounded-lg" />
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-24 w-full rounded-lg" />
          ))}
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
          <HealthSummary overallStatus={data.overall_status} />

          {SECTION_CONFIG.map(({ key, icon, title, storageKey }, sectionIdx) => {
            const sectionStatus = data.overall_status.section_statuses[key] ?? 'healthy';
            const checks = data.checks[key as keyof typeof data.checks] ?? [];
            const isHealthy = sectionStatus === 'healthy';

            // Background always open; others open only when non-healthy
            const smartDefaultOpen = key === 'background' ? true : !isHealthy;

            return (
              <PageSection
                key={key}
                id={storageKey}
                icon={icon}
                title={title()}
                actions={
                  sectionStatus !== 'healthy' && (
                    <Badge variant={STATUS_BADGE_VARIANT[sectionStatus]} dot>
                      {STATUS_BADGE_LABEL[sectionStatus]}
                    </Badge>
                  )
                }
                defaultOpen={smartDefaultOpen}
                collapsible
                storageKey={storageKey}
                className="animate-fade-up"
                style={{ animationDelay: `${sectionIdx * 60}ms` }}
              >
                {key === 'messaging' && <MessagingSection checks={checks} />}
                {key === 'auth' && <AuthSection checks={checks} />}
                {key === 'background' && <BackgroundSection checks={checks} data={data} onMutate={refetch} />}
                {key === 'configuration' && <ConfigSection checks={checks} />}
                {key === 'data_security' && <DataSecuritySection checks={checks} />}
              </PageSection>
            );
          })}
        </>
      )}
    </div>
  );
}
