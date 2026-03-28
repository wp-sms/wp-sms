import { formatDateTime, formatDate } from '@/lib/format';
import { useState, useEffect, useMemo } from 'react';
import { useIntegrations, useIntegrationDetail, useIntegrationConfig } from '@/hooks/use-integrations';
import { IntegrationIcon } from '@/components/integration-icon';
import { IntegrationStatusBadge } from '@/components/integration-status-badge';
import { SchemaForm } from '@/components/schema-form';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, Blocks, Check, ChevronRight, Copy, Loader2, Plus, RefreshCw, Search, Trash2, X, Zap, Play, type LucideIcon } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/error-utils';
import { useConfirm } from '@/components/confirm-provider';
import { INTEGRATION_CATEGORY_LABELS } from '@/lib/constants';
import { pluralize } from '@/lib/utils';
import { api } from '@/lib/api';
import { WooCommerce } from '@/pages/integrations/woocommerce';
import { CF7Verification } from '@/pages/integrations/cf7-verification';
import type { AuthSettings, IntegrationCapability, IntegrationDetail, JsonSchema, PlatformIntegration, ProviderList, SyncSettings, WebhookEndpoint } from '@/lib/api';

interface AppsProps {
  settings?: Required<AuthSettings>;
  onUpdate?: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

function IntegrationCard({ integration, onClick }: {
  integration: PlatformIntegration;
  onClick: () => void;
}) {
  return (
    <Card active={integration.connected} onClick={onClick} className="cursor-pointer">
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <IntegrationIcon icon={integration.icon} size="md" />
            <CardTitle className="text-base">{integration.name}</CardTitle>
          </div>
          <div className="flex items-center gap-2">
            <IntegrationStatusBadge connected={integration.connected} available={integration.available} />
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          </div>
        </div>
        {integration.description && (
          <CardDescription className="line-clamp-2">{integration.description}</CardDescription>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        <Badge variant="outline" className="text-xs">
          {INTEGRATION_CATEGORY_LABELS[integration.category] ?? integration.category}
        </Badge>

        <div className="text-xs text-muted-foreground">
          {integration.triggers} Trigger{integration.triggers !== 1 ? 's' : ''}
          {' · '}
          {integration.actions} Action{integration.actions !== 1 ? 's' : ''}
        </div>

      </CardContent>
    </Card>
  );
}

function CapabilityList({ title, icon: Icon, iconClass, items }: {
  title: string;
  icon: LucideIcon;
  iconClass: string;
  items: Array<{ id: string; name: string; description: string }>;
}) {
  if (items.length === 0) return null;
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base flex items-center gap-2">
          <Icon className={`h-4 w-4 ${iconClass}`} />
          {title} ({items.length})
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-2">
        {items.map((item) => (
          <div key={item.id} className="rounded-md border p-3">
            <div className="text-sm font-medium">{item.name}</div>
            <div className="text-xs text-muted-foreground mt-0.5">{item.description}</div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

function ConnectionSection({ detail, onConfigChange }: {
  detail: IntegrationDetail;
  onConfigChange: () => void;
}) {
  const [credentials, setCredentials] = useState<Record<string, unknown>>({});
  const [connecting, setConnecting] = useState(false);
  const [disconnecting, setDisconnecting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { saveConfig, disconnect } = useIntegrationConfig(onConfigChange);

  if (detail.auth_type === 'gateway') {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Connection</CardTitle>
          <CardDescription>
            {detail.connected
              ? <>Connected via <span className="font-medium">{detail.name}</span> gateway</>
              : (
                <>
                  Connect the gateway first.{' '}
                  <a href="#gateways" className="text-primary hover:underline">
                    Go to Gateways &rarr;
                  </a>
                </>
              )}
          </CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const schema = useMemo<JsonSchema | null>(() => {
    if (detail.auth_type === 'none') return null;
    const properties = detail.auth_schema.properties ?? detail.auth_schema as Record<string, never>;
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
      toast.success(`${detail.name} connected`);
    } catch (e: unknown) {
      setError(getErrorMessage(e, 'Connection failed'));
    } finally {
      setConnecting(false);
    }
  }

  async function handleDisconnect() {
    setDisconnecting(true);
    try {
      await disconnect(detail.id);
      toast.success(`${detail.name} disconnected`);
    } catch {
      toast.error('Failed to disconnect');
    } finally {
      setDisconnecting(false);
    }
  }

  if (detail.connected) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Connection</CardTitle>
          <CardDescription>
            {detail.config?.bot_username
              ? <>Connected as <span className="font-medium">@{String(detail.config.bot_username)}</span></>
              : 'This integration is connected and active.'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="outline" size="sm" onClick={handleDisconnect} disabled={disconnecting}>
            {disconnecting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Disconnect
          </Button>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Connect</CardTitle>
        <CardDescription>Enter your credentials to connect this integration.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <SchemaForm schema={schema} values={credentials} onChange={setCredentials} />
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button size="sm" onClick={handleConnect} disabled={connecting}>
          {connecting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
          Connect
        </Button>
      </CardContent>
    </Card>
  );
}

function CopyButton({ text, label }: { text: string; label?: string }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <Button variant="ghost" size="sm" onClick={handleCopy} className="h-7 px-2">
      {copied ? <Check className="h-3.5 w-3.5 text-success" /> : <Copy className="h-3.5 w-3.5" />}
      {label && <span className="ml-1 text-xs">{label}</span>}
    </Button>
  );
}

function WebhookEndpointsSection({ onConfigChange }: { onConfigChange: () => void }) {
  const [endpoints, setEndpoints] = useState<WebhookEndpoint[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [label, setLabel] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [newEndpoint, setNewEndpoint] = useState<WebhookEndpoint | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const confirm = useConfirm();

  useEffect(() => {
    api.get<{ endpoints: WebhookEndpoint[] }>('integrations/webhook/endpoints')
      .then((res) => setEndpoints(res.endpoints))
      .catch(() => toast.error('Failed to load webhook endpoints'))
      .finally(() => setLoading(false));
  }, []);

  async function handleCreate() {
    if (!label.trim()) return;
    setCreating(true);
    try {
      const endpoint = await api.post<WebhookEndpoint>('integrations/webhook/endpoints', { label: label.trim() });
      setNewEndpoint(endpoint);
      setEndpoints((prev) => [...prev, { id: endpoint.id, label: endpoint.label, url: endpoint.url, created_at: endpoint.created_at }]);
      setLabel('');
      setShowForm(false);
      onConfigChange();
      toast.success('Webhook endpoint created');
    } catch {
      toast.error('Failed to create endpoint');
    } finally {
      setCreating(false);
    }
  }

  async function handleDelete(id: string) {
    const confirmed = await confirm({
      title: 'Delete webhook endpoint?',
      description: 'Flows using this endpoint will stop firing.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!confirmed) return;
    setDeletingId(id);
    try {
      await api.del(`integrations/webhook/endpoints/${id}`);
      setEndpoints((prev) => prev.filter((e) => e.id !== id));
      if (newEndpoint?.id === id) setNewEndpoint(null);
      onConfigChange();
      toast.success('Endpoint deleted');
    } catch {
      toast.error('Failed to delete endpoint');
    } finally {
      setDeletingId(null);
    }
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-base">Webhook Endpoints</CardTitle>
            <CardDescription>
              Create endpoints to receive webhooks from external services.
            </CardDescription>
          </div>
          {!showForm && (
            <Button variant="outline" size="sm" onClick={() => setShowForm(true)}>
              <Plus className="mr-1 h-4 w-4" />
              Add Endpoint
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {showForm && (
          <div className="flex items-end gap-2 rounded-md border p-3 bg-muted/30">
            <div className="flex-1 space-y-1">
              <label className="text-sm font-medium">Label</label>
              <Input
                placeholder="e.g. Stripe, GitHub, Zapier..."
                value={label}
                onChange={(e) => setLabel(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
              />
            </div>
            <Button size="sm" onClick={handleCreate} disabled={creating || !label.trim()}>
              {creating && <Loader2 className="mr-1 h-4 w-4 animate-spin" />}
              Create
            </Button>
            <Button variant="ghost" size="sm" onClick={() => { setShowForm(false); setLabel(''); }}>
              Cancel
            </Button>
          </div>
        )}

        {newEndpoint?.secret && (
          <div className="rounded-md border border-warning/40 bg-warning/[0.06] p-4 space-y-3">
            <p className="text-sm font-medium text-warning">
              Save your signing secret now — it won&apos;t be shown again.
            </p>
            <div className="flex items-center gap-2">
              <code className="flex-1 text-xs bg-white dark:bg-black/30 rounded px-2 py-1.5 font-mono break-all border">
                {newEndpoint.secret}
              </code>
              <CopyButton text={newEndpoint.secret} label="Copy" />
            </div>
            <div className="text-xs text-muted-foreground space-y-1">
              <p className="font-medium">Signing requests:</p>
              <p>Compute HMAC-SHA256 of the request body using this secret, and send it in the <code className="bg-muted px-1 rounded">X-Webhook-Signature</code> header.</p>
              <pre className="bg-white dark:bg-black/30 rounded p-2 mt-1.5 overflow-x-auto border text-[11px]">{`curl -X POST ${newEndpoint.url} \\
  -H "Content-Type: application/json" \\
  -H "X-Webhook-Signature: $(echo -n '{"test":1}' | openssl dgst -sha256 -hmac '${newEndpoint.secret}' | cut -d' ' -f2)" \\
  -d '{"test":1}'`}</pre>
            </div>
            <Button variant="outline" size="sm" onClick={() => setNewEndpoint(null)}>
              Dismiss
            </Button>
          </div>
        )}

        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-14 w-full" />
            <Skeleton className="h-14 w-full" />
          </div>
        ) : endpoints.length === 0 ? (
          <p className="text-sm text-muted-foreground py-2">
            No webhook endpoints yet. Create one to start receiving external events.
          </p>
        ) : (
          <div className="space-y-2">
            {endpoints.map((ep) => (
              <div key={ep.id} className="flex items-center justify-between rounded-md border p-3 gap-3">
                <div className="min-w-0 flex-1">
                  <div className="text-sm font-medium">{ep.label}</div>
                  <div className="flex items-center gap-1 mt-0.5">
                    <code className="text-xs text-muted-foreground truncate">{ep.url}</code>
                    <CopyButton text={ep.url} />
                  </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <span className="text-xs text-muted-foreground">
                    {formatDate(ep.created_at)}
                  </span>
                  <Button
                    variant="ghost"
                    size="icon-md"
                    className="text-muted-foreground hover:text-destructive"
                    onClick={() => handleDelete(ep.id)}
                    disabled={deletingId === ep.id}
                  >
                    {deletingId === ep.id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Trash2 className="h-3.5 w-3.5" />
                    )}
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function SettingsSection({ integrationId, settings, onUpdate }: {
  integrationId: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}) {
  switch (integrationId) {
    case 'woocommerce':
      return <WooCommerce settings={settings} onUpdate={onUpdate} />;
    case 'contactform7':
      return <CF7Verification settings={settings} onUpdate={onUpdate} />;
    default:
      return null;
  }
}

function CapabilitiesSection({ capabilities }: { capabilities: IntegrationCapability[] }) {
  const hasAny = capabilities.some((c) => c.supported);
  if (!hasAny) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Capabilities</CardTitle>
        <CardDescription>Features supported by this integration.</CardDescription>
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

const POLL_INTERVALS = [
  { value: 900, label: '15 minutes' },
  { value: 3600, label: '1 hour' },
  { value: 21600, label: '6 hours' },
  { value: 86400, label: 'Daily' },
] as const;

function SyncSettingsSection({ detail, onRefresh }: {
  detail: IntegrationDetail;
  onRefresh: () => void;
}) {
  const hasSyncCapability = detail.capabilities?.some((c) => c.id === 'contact_sync' && c.supported);
  const hasLists = detail.capabilities?.some((c) => c.id === 'list_management' && c.supported);
  const shouldShow = hasSyncCapability && detail.connected;

  const [settings, setSettings] = useState<SyncSettings>(detail.sync_settings ?? {
    auto_push: false,
    push_tags: false,
    poll_interval: 3600,
    poll_enabled: true,
    default_list_id: '',
    remove_on_delete: false,
  });
  const [lists, setLists] = useState<ProviderList[]>([]);
  const [loadingLists, setLoadingLists] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!shouldShow || !hasLists) return;
    setLoadingLists(true);
    api.get<{ lists: ProviderList[] }>(`integrations/${detail.id}/lists`)
      .then((res) => setLists(res.lists))
      .catch(() => setLists([]))
      .finally(() => setLoadingLists(false));
  }, [detail.id, shouldShow, hasLists]);

  if (!shouldShow) return null;

  async function handleSave() {
    setSaving(true);
    try {
      await api.put(`integrations/${detail.id}/sync-settings`, settings);
      toast.success('Sync settings saved');
      onRefresh();
    } catch {
      toast.error('Failed to save sync settings');
    } finally {
      setSaving(false);
    }
  }

  const update = <K extends keyof SyncSettings>(key: K, value: SyncSettings[K]) =>
    setSettings((prev) => ({ ...prev, [key]: value }));

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Sync Settings</CardTitle>
        <CardDescription>Configure how contacts are synced with this provider.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {hasLists && (
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Default List</label>
            {loadingLists ? (
              <Skeleton className="h-9 w-full" />
            ) : (
              <Select value={settings.default_list_id} onValueChange={(v) => update('default_list_id', v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Select a list..." />
                </SelectTrigger>
                <SelectContent>
                  {lists.map((list) => (
                    <SelectItem key={list.id} value={list.id}>
                      {list.name} ({list.contact_count})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>
        )}

        <div className="flex items-center justify-between">
          <div>
            <label className="text-sm font-medium">Auto-push contacts</label>
            <p className="text-xs text-muted-foreground">Push new and updated contacts to the provider automatically.</p>
          </div>
          <Switch checked={settings.auto_push} onCheckedChange={(v) => update('auto_push', v)} />
        </div>

        <div className="flex items-center justify-between">
          <div>
            <label className="text-sm font-medium">Push tags</label>
            <p className="text-xs text-muted-foreground">Include contact tags when pushing to provider.</p>
          </div>
          <Switch checked={settings.push_tags} onCheckedChange={(v) => update('push_tags', v)} />
        </div>

        <div className="flex items-center justify-between">
          <div>
            <label className="text-sm font-medium">Suppression polling</label>
            <p className="text-xs text-muted-foreground">Poll for unsubscribes, bounces, and complaints.</p>
          </div>
          <Switch checked={settings.poll_enabled} onCheckedChange={(v) => update('poll_enabled', v)} />
        </div>

        {settings.poll_enabled && (
          <div className="space-y-1.5 pl-4">
            <label className="text-sm font-medium">Poll interval</label>
            <Select value={String(settings.poll_interval)} onValueChange={(v) => update('poll_interval', Number(v))}>
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {POLL_INTERVALS.map((opt) => (
                  <SelectItem key={opt.value} value={String(opt.value)}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}

        <div className="flex items-center justify-between">
          <div>
            <label className="text-sm font-medium">Remove on delete</label>
            <p className="text-xs text-muted-foreground">Remove contact from provider when deleted locally.</p>
          </div>
          <Switch checked={settings.remove_on_delete} onCheckedChange={(v) => update('remove_on_delete', v)} />
        </div>

        <Button size="sm" onClick={handleSave} disabled={saving}>
          {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
          Save Settings
        </Button>
      </CardContent>
    </Card>
  );
}

function SyncStatusSection({ detail, onRefresh }: {
  detail: IntegrationDetail;
  onRefresh: () => void;
}) {
  const [syncing, setSyncing] = useState(false);
  const [polling, setPolling] = useState(false);

  const hasSyncCapability = detail.capabilities?.some((c) => c.id === 'contact_sync' && c.supported);
  if (!hasSyncCapability || !detail.connected || !detail.sync_status) return null;

  const status = detail.sync_status;

  async function handleSyncNow() {
    setSyncing(true);
    try {
      const res = await api.post<{ success: boolean; dispatched: number }>(`integrations/${detail.id}/sync`, {});
      toast.success(`Sync started: ${res.dispatched} contacts queued`);
      onRefresh();
    } catch {
      toast.error('Failed to start sync');
    } finally {
      setSyncing(false);
    }
  }

  async function handlePollNow() {
    setPolling(true);
    try {
      const res = await api.post<{ success: boolean; events: number }>(`integrations/${detail.id}/poll`, {});
      toast.success(`Poll complete: ${res.events} events processed`);
      onRefresh();
    } catch {
      toast.error('Failed to poll');
    } finally {
      setPolling(false);
    }
  }

  function formatSyncDate(iso: string | null) {
    if (!iso) return 'Never';
    return formatDateTime(iso);
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Sync Status</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <span className="text-muted-foreground">Last push</span>
            <p className="font-medium">{formatSyncDate(status.last_push_at)}</p>
          </div>
          <div>
            <span className="text-muted-foreground">Last poll</span>
            <p className="font-medium">{formatSyncDate(status.last_poll_at)}</p>
          </div>
          <div>
            <span className="text-muted-foreground">Total pushed</span>
            <p className="font-medium">{status.total_pushed ?? 0}</p>
          </div>
          {status.last_error && (
            <div className="col-span-2">
              <span className="text-muted-foreground">Last error</span>
              <p className="text-sm text-destructive">{status.last_error}</p>
            </div>
          )}
        </div>

        <div className="flex gap-2 pt-1">
          <Button variant="outline" size="sm" onClick={handleSyncNow} disabled={syncing}>
            {syncing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
            Sync Now
          </Button>
          <Button variant="outline" size="sm" onClick={handlePollNow} disabled={polling}>
            {polling ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
            Poll Now
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

function AppDetailPage({ integrationId, settings, onUpdate, onBack }: {
  integrationId: string;
  settings?: Required<AuthSettings>;
  onUpdate?: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  onBack: () => void;
}) {
  const { detail, loading, refetch } = useIntegrationDetail(integrationId);

  if (loading || !detail) {
    return (
      <div className="space-y-6">
        <Button variant="ghost" size="sm" className="-ml-2" onClick={onBack}>
          <ArrowLeft className="mr-1 h-4 w-4" />
          Back to Integrations
        </Button>
        <div className="space-y-4">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" className="-ml-2" onClick={onBack}>
        <ArrowLeft className="mr-1 h-4 w-4" />
        Back to Integrations
      </Button>

      <div className="flex items-center gap-3">
        <IntegrationIcon icon={detail.icon} size="lg" />
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-base font-semibold">{detail.name}</h2>
            <IntegrationStatusBadge connected={detail.connected} available={detail.available} />
            <Badge variant="outline" className="text-xs">
              {INTEGRATION_CATEGORY_LABELS[detail.category] ?? detail.category}
            </Badge>
          </div>
          <p className="text-sm text-muted-foreground">{detail.description}</p>
        </div>
      </div>

      {!detail.available && (
        <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
          {detail.name} is not installed. Settings can be pre-configured but won&apos;t take effect until the plugin is active.
        </p>
      )}

      {detail.auth_type === 'webhook_secret' ? (
        <WebhookEndpointsSection onConfigChange={refetch} />
      ) : (
        <ConnectionSection detail={detail} onConfigChange={refetch} />
      )}

      {detail.capabilities && detail.capabilities.some((c) => c.supported) && (
        <CapabilitiesSection capabilities={detail.capabilities} />
      )}

      {settings && onUpdate && (
        <SettingsSection integrationId={detail.id} settings={settings} onUpdate={onUpdate} />
      )}

      <SyncSettingsSection detail={detail} onRefresh={refetch} />
      <SyncStatusSection detail={detail} onRefresh={refetch} />

      <CapabilityList title="Triggers" icon={Zap} iconClass="text-amber-500" items={detail.triggers} />
      <CapabilityList title="Actions" icon={Play} iconClass="text-blue-500" items={detail.actions} />

      {detail.triggers.length === 0 && detail.actions.length === 0 && (
        <p className="text-sm text-muted-foreground">
          This integration has no triggers or actions yet.
        </p>
      )}

    </div>
  );
}

export function IntegrationsPage({ settings, onUpdate }: AppsProps) {
  const { integrations, loading } = useIntegrations();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');

  const allCategories = useMemo(() => {
    const set = new Set<string>();
    integrations.forEach((i) => set.add(i.category));
    return Array.from(set).sort();
  }, [integrations]);

  const filtered = useMemo(() => {
    return integrations.filter((i) => {
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        if (!i.name.toLowerCase().includes(q) && !i.id.toLowerCase().includes(q)) return false;
      }
      if (categoryFilter !== 'all' && i.category !== categoryFilter) return false;
      return true;
    });
  }, [integrations, searchQuery, categoryFilter]);

  if (selectedId) {
    return (
      <AppDetailPage
        integrationId={selectedId}
        settings={settings}
        onUpdate={onUpdate}
        onBack={() => setSelectedId(null)}
      />
    );
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <Skeleton className="h-9 w-64" />
          <Skeleton className="h-9 w-40" />
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-48" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader icon={Blocks} title="Integrations" metadata={pluralize(integrations.length, 'integration')} />
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            type="text"
            placeholder="Search apps..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>
        {allCategories.length > 1 && (
          <Select value={categoryFilter} onValueChange={setCategoryFilter}>
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Categories</SelectItem>
              {allCategories.map((cat) => (
                <SelectItem key={cat} value={cat}>
                  {INTEGRATION_CATEGORY_LABELS[cat] ?? cat}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </div>

      {filtered.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filtered.map((integration) => (
            <IntegrationCard
              key={integration.id}
              integration={integration}
              onClick={() => setSelectedId(integration.id)}
            />
          ))}
        </div>
      ) : (
        <div className="flex items-center justify-center py-12">
          <p className="text-sm text-muted-foreground">No apps match your filters</p>
        </div>
      )}
    </div>
  );
}
