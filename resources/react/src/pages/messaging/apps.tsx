import { useState, useMemo } from 'react';
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
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, Loader2, Search, Zap, Play, type LucideIcon } from 'lucide-react';
import { toast } from 'sonner';
import { AUTH_INTEGRATION_IDS, INTEGRATION_CATEGORY_LABELS } from '@/lib/constants';
import { getConfig } from '@/lib/api';
import type { AuthSettings, ContactForm7Settings, IntegrationDetail, JsonSchema, PlatformIntegration } from '@/lib/api';

interface AppsProps {
  settings?: Required<AuthSettings>;
  onUpdate?: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

function IntegrationCard({ integration, onClick }: {
  integration: PlatformIntegration;
  onClick: () => void;
}) {
  return (
    <Card className={integration.connected ? 'border-l-2 border-l-primary' : ''}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <IntegrationIcon icon={integration.icon} size="md" />
            <CardTitle className="text-base">{integration.name}</CardTitle>
          </div>
          <IntegrationStatusBadge connected={integration.connected} available={integration.available} />
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

        <Button variant="outline" size="sm" onClick={onClick} className="w-full">
          {integration.auth_type !== 'none' && !integration.connected ? 'Configure' : 'View Details'}
        </Button>
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
      const err = e as Record<string, unknown> | null;
      setError(err?.message ? String(err.message) : 'Connection failed');
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

function SettingsSection({ integrationId, settings, onUpdate }: {
  integrationId: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}) {
  switch (integrationId) {
    case 'contactform7': {
      const cf7 = settings.contact_form_7 ?? {};
      const update = (partial: Partial<ContactForm7Settings>) =>
        onUpdate('contact_form_7', { ...cf7, ...partial });

      return (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Settings</CardTitle>
            <CardDescription>
              Configure notification behavior for this integration.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Field className="flex items-center justify-between">
              <div>
                <FieldLabel htmlFor="cf7_notifications_enabled">Enable notifications</FieldLabel>
                <FieldDescription>
                  Show the WSMS notification panel in the Contact Form 7 editor for configuring SMS, email, WhatsApp, or Telegram notifications per form.
                </FieldDescription>
              </div>
              <Switch
                id="cf7_notifications_enabled"
                checked={cf7.notifications_enabled !== false}
                onCheckedChange={(v) => update({ notifications_enabled: v })}
              />
            </Field>
          </CardContent>
        </Card>
      );
    }
    default:
      return null;
  }
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
          Back to Apps
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
        Back to Apps
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

      <ConnectionSection detail={detail} onConfigChange={refetch} />

      {settings && onUpdate && (
        <SettingsSection integrationId={detail.id} settings={settings} onUpdate={onUpdate} />
      )}

      <CapabilityList title="Triggers" icon={Zap} iconClass="text-amber-500" items={detail.triggers} />
      <CapabilityList title="Actions" icon={Play} iconClass="text-blue-500" items={detail.actions} />

      {detail.triggers.length === 0 && detail.actions.length === 0 && (
        <p className="text-sm text-muted-foreground">
          This integration has no triggers or actions yet.
        </p>
      )}

      {AUTH_INTEGRATION_IDS.has(detail.id) && (
        <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
          Looking for authentication settings?{' '}
          <a href={`${getConfig().adminUrl}admin.php?page=wsms-auth#integrations`} className="text-primary hover:underline">
            Auth &rarr; Integrations
          </a>
        </p>
      )}
    </div>
  );
}

export function Apps({ settings, onUpdate }: AppsProps) {
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
