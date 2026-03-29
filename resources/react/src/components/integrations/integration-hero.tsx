import { __, sprintf, _n } from '@wordpress/i18n';
import { useState } from 'react';
import { IntegrationIcon } from '@/components/integration-icon';
import { IntegrationStatusBadge } from '@/components/integration-status-badge';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import { Loader2, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { useIntegrationConfig } from '@/hooks/use-integrations';
import { INTEGRATION_CATEGORY_LABELS } from '@/lib/constants';
import type { IntegrationDetail } from '@/lib/api';

interface IntegrationHeroProps {
  detail: IntegrationDetail;
  onConfigChange: () => void;
}

export function IntegrationHero({ detail, onConfigChange }: IntegrationHeroProps) {
  const [disconnecting, setDisconnecting] = useState(false);
  const { disconnect } = useIntegrationConfig(onConfigChange);

  const supportedCapabilities = (detail.capabilities ?? []).filter((c) => c.supported);

  async function handleDisconnect() {
    setDisconnecting(true);
    try {
      await disconnect(detail.id);
      toast.success(sprintf(__('%s disconnected', 'wp-sms'), detail.name));
    } catch {
      toast.error(__('Failed to disconnect', 'wp-sms'));
    } finally {
      setDisconnecting(false);
    }
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <div className="flex items-start justify-between gap-4">
            <div className="flex items-start gap-4">
              <IntegrationIcon icon={detail.icon} size="xl" className="mt-0.5" />
              <div className="space-y-1.5">
                <div className="flex items-center gap-2 flex-wrap">
                  <h2 className="text-xl font-extrabold">{detail.name}</h2>
                  <IntegrationStatusBadge connected={detail.connected} available={detail.available} />
                  <Badge variant="outline" className="text-xs">
                    {INTEGRATION_CATEGORY_LABELS[detail.category] ?? detail.category}
                  </Badge>
                </div>
                <p className="text-sm text-muted-foreground">{detail.description}</p>
              </div>
            </div>

            <HeroAction
              detail={detail}
              disconnecting={disconnecting}
              onDisconnect={handleDisconnect}
            />
          </div>
        </CardHeader>

        {supportedCapabilities.length > 0 && (
          <CardContent>
            <div className="flex flex-wrap gap-1.5">
              {supportedCapabilities.map((cap) => (
                <Badge key={cap.id} variant="neutral" className="text-xs font-medium">
                  {cap.name}
                </Badge>
              ))}
              {detail.triggers.length > 0 && (
                <Badge variant="neutral" className="text-xs font-medium">
                  {sprintf(_n('%d Trigger', '%d Triggers', detail.triggers.length, 'wp-sms'), detail.triggers.length)}
                </Badge>
              )}
              {detail.actions.length > 0 && (
                <Badge variant="neutral" className="text-xs font-medium">
                  {sprintf(_n('%d Action', '%d Actions', detail.actions.length, 'wp-sms'), detail.actions.length)}
                </Badge>
              )}
            </div>
          </CardContent>
        )}
      </Card>

      {!detail.available && (
        <Alert variant="warning">
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>{__('Not Installed', 'wp-sms')}</AlertTitle>
          <AlertDescription>
            {sprintf(__('%s is not installed. Settings can be pre-configured but won\'t take effect until the plugin is active.', 'wp-sms'), detail.name)}
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
}

function HeroAction({ detail, disconnecting, onDisconnect }: {
  detail: IntegrationDetail;
  disconnecting: boolean;
  onDisconnect: () => void;
}) {
  if (detail.auth_type === 'none' || detail.auth_type === 'webhook_secret' || !detail.connected) return null;

  const label = detail.auth_type === 'gateway'
    ? sprintf(__('Connected via %s', 'wp-sms'), detail.name)
    : typeof detail.config?.bot_username === 'string'
      ? <>{__('Connected as', 'wp-sms')} <span className="font-medium">@{detail.config.bot_username}</span></>
      : null;

  return (
    <div className="flex items-center gap-2 shrink-0">
      {label && <span className="text-xs text-muted-foreground">{label}</span>}
      <Button variant="outline" size="sm" onClick={onDisconnect} disabled={disconnecting}>
        {disconnecting && <Loader2 className="me-1 h-4 w-4 animate-spin" />}
        {__('Disconnect', 'wp-sms')}
      </Button>
    </div>
  );
}

export function IntegrationHeroSkeleton() {
  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <div className="flex items-start gap-4">
            <Skeleton className="h-14 w-14 rounded-xl" />
            <div className="space-y-2 flex-1">
              <Skeleton className="h-7 w-48" />
              <Skeleton className="h-4 w-80" />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="flex gap-1.5">
            <Skeleton className="h-5 w-24 rounded-full" />
            <Skeleton className="h-5 w-20 rounded-full" />
            <Skeleton className="h-5 w-16 rounded-full" />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
