import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from 'react';
import { SchemaForm } from '@/components/schema-form';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertTriangle, Check, X, Info } from 'lucide-react';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/error-utils';
import { useIntegrationConfig } from '@/hooks/use-integrations';
import { formatDateTime, formatDate } from '@/lib/format';
import { api } from '@/lib/api';
import type { IntegrationDetail, JsonSchema, WebhookEndpoint } from '@/lib/api';

interface OverviewTabProps {
  detail: IntegrationDetail;
  onConfigChange: () => void;
}

export function IntegrationOverviewTab({ detail, onConfigChange }: OverviewTabProps) {
  const capabilities = detail.capabilities ?? [];

  return (
    <div className="space-y-6">
      <ConnectionContent detail={detail} onConfigChange={onConfigChange} />
      {capabilities.length > 0 && <CapabilitiesOverview capabilities={capabilities} />}
    </div>
  );
}

function ConnectionContent({ detail, onConfigChange }: { detail: IntegrationDetail; onConfigChange: () => void }) {
  if (detail.auth_type === 'webhook_secret') {
    return <WebhookEndpointsList />;
  }

  if (detail.auth_type === 'gateway' && !detail.connected) {
    return <GatewayPrompt />;
  }

  if (detail.connected) {
    return <StatusDashboard detail={detail} />;
  }

  return <CredentialForm detail={detail} onConfigChange={onConfigChange} />;
}

function CredentialForm({ detail, onConfigChange }: { detail: IntegrationDetail; onConfigChange: () => void }) {
  const [credentials, setCredentials] = useState<Record<string, unknown>>({});
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { saveConfig } = useIntegrationConfig(onConfigChange);

  const schema = useMemo<JsonSchema | null>(() => {
    if (detail.auth_type === 'none') return null;
    const properties = detail.auth_schema.properties ?? detail.auth_schema as unknown as Record<string, never>;
    return {
      type: 'object',
      properties,
      required: Object.entries(properties)
        .filter(([, v]) => (v as Record<string, unknown>).required)
        .map(([k]) => k),
    };
  }, [detail.auth_type, detail.auth_schema]);

  if (!schema) return null;

  async function handleConnect() {
    setConnecting(true);
    setError(null);
    try {
      await saveConfig(detail.id, credentials);
      toast.success(sprintf(__('%s connected', 'wp-sms'), detail.name));
    } catch (e: unknown) {
      setError(getErrorMessage(e, 'Connection failed'));
    } finally {
      setConnecting(false);
    }
  }

  const hasImport = detail.capabilities?.some((c) => c.id === 'contact_import' && c.supported);
  const hasSync = detail.capabilities?.some((c) => c.id === 'contact_sync' && c.supported);
  const hasPoll = detail.capabilities?.some((c) => c.id === 'suppression_polling' && c.supported);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{__('Connect', 'wp-sms')}</CardTitle>
        <CardDescription>{__('Enter your credentials to connect this integration.', 'wp-sms')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <SchemaForm schema={schema} values={credentials} onChange={setCredentials} />
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button size="sm" onClick={handleConnect} disabled={connecting}>
          {connecting && <Loader2 className="me-2 h-4 w-4 animate-spin" />}
          Connect
        </Button>

        {(hasImport || hasSync || hasPoll) && (
          <div className="text-sm text-muted-foreground space-y-1 border-t pt-4">
            <p className="font-medium">After connecting, you&apos;ll be able to:</p>
            <ul className="list-disc list-inside text-xs space-y-0.5">
              {hasImport && <li>Import contacts from {detail.name}</li>}
              {hasSync && <li>Sync contacts automatically</li>}
              {hasPoll && <li>Poll for suppressions and bounces</li>}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function GatewayPrompt() {
  return (
    <Alert variant="info">
      <Info className="h-4 w-4" />
      <AlertTitle>{__('Gateway Required', 'wp-sms')}</AlertTitle>
      <AlertDescription>
        Connect the gateway first.{' '}
        <a href="#gateways" className="font-medium underline hover:no-underline">
          Go to Gateways &rarr;
        </a>
      </AlertDescription>
    </Alert>
  );
}

function StatusDashboard({ detail }: { detail: IntegrationDetail }) {
  const hasImport = detail.capabilities?.some((c) => c.id === 'contact_import' && c.supported);
  const hasSync = detail.capabilities?.some((c) => c.id === 'contact_sync' && c.supported);
  const importStats = detail.import_stats;
  const syncStatus = detail.sync_status;
  const hasStats = (hasImport && importStats) || (hasSync && syncStatus);

  if (detail.auth_type === 'none' && !hasStats) {
    return (
      <Alert variant="success">
        <Check className="h-4 w-4" />
        <AlertTitle>{__('Active', 'wp-sms')}</AlertTitle>
        <AlertDescription>
          {__('This integration is active and works automatically. No configuration needed.', 'wp-sms')}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{__('Status', 'wp-sms')}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {hasStats && (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <StatCard label="Status" value="Connected" />
            {hasImport && importStats && (
              <StatCard label="Contacts imported" value={importStats.total_synced} />
            )}
            {hasSync && syncStatus && (
              <>
                <StatCard label="Last sync" value={syncStatus.last_push_at ? formatDateTime(syncStatus.last_push_at) : 'Never'} />
                <StatCard label="Total pushed" value={syncStatus.total_pushed ?? 0} />
              </>
            )}
          </div>
        )}

        {syncStatus?.last_error && (
          <Alert variant="destructive">
            <AlertTriangle className="h-4 w-4" />
            <AlertTitle>{__('Sync Error', 'wp-sms')}</AlertTitle>
            <AlertDescription>{syncStatus.last_error}</AlertDescription>
          </Alert>
        )}

        {importStats?.last_error && (
          <Alert variant="destructive">
            <AlertTriangle className="h-4 w-4" />
            <AlertTitle>{__('Import Error', 'wp-sms')}</AlertTitle>
            <AlertDescription>{importStats.last_error}</AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}

function StatCard({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-md border p-4">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className="text-lg font-semibold mt-0.5">{String(value)}</div>
    </div>
  );
}

function CapabilitiesOverview({ capabilities }: { capabilities: NonNullable<IntegrationDetail['capabilities']> }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{__('Capabilities', 'wp-sms')}</CardTitle>
        <CardDescription>{__('Features supported by this integration.', 'wp-sms')}</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="space-y-2">
          {capabilities.map((cap) => (
            <div key={cap.id} className="flex items-center justify-between text-sm">
              <span>{cap.name}</span>
              <span className="flex items-center gap-1.5">
                {cap.supported ? (
                  <>
                    <Check className="h-3.5 w-3.5 text-success" />
                    <span className="text-success">{cap.note ?? 'Supported'}</span>
                  </>
                ) : (
                  <>
                    <X className="h-3.5 w-3.5 text-muted-foreground" />
                    <span className="text-muted-foreground">{cap.note ?? 'Not supported'}</span>
                  </>
                )}
              </span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

function WebhookEndpointsList() {
  const [endpoints, setEndpoints] = useState<WebhookEndpoint[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get<{ endpoints: WebhookEndpoint[] }>('integrations/webhook/endpoints')
      .then((res) => setEndpoints(res.endpoints))
      .catch(() => toast.error(__('Failed to load webhook endpoints', 'wp-sms')))
      .finally(() => setLoading(false));
  }, []);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{__('Webhook Endpoints', 'wp-sms')}</CardTitle>
        <CardDescription>
          {__('Endpoints configured to receive webhooks from external services.', 'wp-sms')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </div>
        ) : endpoints.length === 0 ? (
          <p className="text-sm text-muted-foreground py-2">
            {__('No webhook endpoints configured yet.', 'wp-sms')}
          </p>
        ) : (
          <div className="space-y-2">
            {endpoints.map((ep) => (
              <div key={ep.id} className="flex items-center justify-between rounded-md border p-3 gap-3">
                <div className="min-w-0 flex-1">
                  <div className="text-sm font-medium">{ep.label}</div>
                  <code className="text-xs text-muted-foreground truncate block mt-0.5">{ep.url}</code>
                </div>
                <span className="text-xs text-muted-foreground shrink-0">
                  {formatDate(ep.created_at)}
                </span>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
