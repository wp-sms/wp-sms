import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from 'react';
import { PageSection } from '@/components/ui/page-section';
import { SwitchField } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RefreshCw, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { formatDateTime } from '@/lib/format';
import { ImportSection } from '@/components/integrations/import-section';
import { api } from '@/lib/api';
import type { IntegrationDetail, SyncSettings, ProviderList } from '@/lib/api';

const POLL_INTERVALS = [
  { value: 900, label: '15 minutes' },
  { value: 3600, label: '1 hour' },
  { value: 21600, label: '6 hours' },
  { value: 86400, label: 'Daily' },
] as const;

interface DataTabProps {
  detail: IntegrationDetail;
  onRefresh: () => void;
}

export function IntegrationDataTab({ detail, onRefresh }: DataTabProps) {
  const hasImport = detail.capabilities?.some((c) => c.id === 'contact_import' && c.supported);
  const hasSync = detail.capabilities?.some((c) => c.id === 'contact_sync' && c.supported);

  return (
    <div className="space-y-6">
      {hasImport && detail.connected && (
        <ImportSection
          integrationId={detail.id}
          importSettings={detail.import_settings}
          importStats={detail.import_stats}
          onUpdate={onRefresh}
        />
      )}

      {hasSync && detail.connected && (
        <SyncSection detail={detail} onRefresh={onRefresh} />
      )}
    </div>
  );
}

function SyncSection({ detail, onRefresh }: { detail: IntegrationDetail; onRefresh: () => void }) {
  const hasLists = detail.capabilities?.some((c) => c.id === 'list_management' && c.supported);

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
  const [syncing, setSyncing] = useState(false);
  const [polling, setPolling] = useState(false);

  const status = detail.sync_status;

  useEffect(() => {
    if (!hasLists) return;
    setLoadingLists(true);
    api.get<{ lists: ProviderList[] }>(`integrations/${detail.id}/lists`)
      .then((res) => setLists(res.lists))
      .catch(() => setLists([]))
      .finally(() => setLoadingLists(false));
  }, [detail.id, hasLists]);

  async function handleSave() {
    setSaving(true);
    try {
      await api.put(`integrations/${detail.id}/sync-settings`, settings);
      toast.success(__('Sync settings saved', 'wp-sms'));
      onRefresh();
    } catch {
      toast.error(__('Failed to save sync settings', 'wp-sms'));
    } finally {
      setSaving(false);
    }
  }

  async function handleSyncNow() {
    setSyncing(true);
    try {
      const res = await api.post<{ success: boolean; dispatched: number }>(`integrations/${detail.id}/sync`, {});
      toast.success(sprintf(__('Sync started: %s contacts queued', 'wp-sms'), res.dispatched));
      onRefresh();
    } catch {
      toast.error(__('Failed to start sync', 'wp-sms'));
    } finally {
      setSyncing(false);
    }
  }

  async function handlePollNow() {
    setPolling(true);
    try {
      const res = await api.post<{ success: boolean; events: number }>(`integrations/${detail.id}/poll`, {});
      toast.success(sprintf(__('Poll complete: %s events processed', 'wp-sms'), res.events));
      onRefresh();
    } catch {
      toast.error(__('Failed to poll', 'wp-sms'));
    } finally {
      setPolling(false);
    }
  }

  const update = <K extends keyof SyncSettings>(key: K, value: SyncSettings[K]) =>
    setSettings((prev) => ({ ...prev, [key]: value }));

  const actions = (
    <div className="flex gap-2">
      <Button variant="outline" size="sm" onClick={handleSyncNow} disabled={syncing}>
        {syncing ? <Loader2 className="me-1 h-4 w-4 animate-spin" /> : <RefreshCw className="me-1 h-4 w-4" />}
        Sync Now
      </Button>
      <Button variant="outline" size="sm" onClick={handlePollNow} disabled={polling}>
        {polling ? <Loader2 className="me-1 h-4 w-4 animate-spin" /> : <RefreshCw className="me-1 h-4 w-4" />}
        Poll Now
      </Button>
    </div>
  );

  return (
    <PageSection
      icon={RefreshCw}
      title={__('Sync Settings', 'wp-sms')}
      description={__('Configure how contacts are synced with this provider.', 'wp-sms')}
      actions={status ? actions : undefined}
      collapsible
      defaultOpen
      storageKey="int-sync"
    >
      <div className="space-y-4">
        {status && (
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span className="text-muted-foreground">{__('Last push', 'wp-sms')}</span>
              <p className="font-medium">{status.last_push_at ? formatDateTime(status.last_push_at) : __('Never', 'wp-sms')}</p>
            </div>
            <div>
              <span className="text-muted-foreground">{__('Last poll', 'wp-sms')}</span>
              <p className="font-medium">{status.last_poll_at ? formatDateTime(status.last_poll_at) : __('Never', 'wp-sms')}</p>
            </div>
            <div>
              <span className="text-muted-foreground">{__('Total pushed', 'wp-sms')}</span>
              <p className="font-medium">{status.total_pushed ?? 0}</p>
            </div>
            {status.last_error && (
              <div className="col-span-2">
                <span className="text-muted-foreground">{__('Last error', 'wp-sms')}</span>
                <p className="text-sm text-destructive">{status.last_error}</p>
              </div>
            )}
          </div>
        )}

        {status && <hr className="border-border" />}

        {hasLists && (
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Default List</label>
            {loadingLists ? (
              <Skeleton className="h-9 w-full" />
            ) : (
              <Select value={settings.default_list_id} onValueChange={(v) => update('default_list_id', v)}>
                <SelectTrigger>
                  <SelectValue placeholder={__('Select a list...', 'wp-sms')} />
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

        <SwitchField
          id="sync-auto-push"
          label={__('Auto-push contacts', 'wp-sms')}
          description={__('Push new and updated contacts to the provider automatically.', 'wp-sms')}
          checked={settings.auto_push}
          onCheckedChange={(v) => update('auto_push', v)}
        />

        <SwitchField
          id="sync-push-tags"
          label={__('Push tags', 'wp-sms')}
          description={__('Include contact tags when pushing to provider.', 'wp-sms')}
          checked={settings.push_tags}
          onCheckedChange={(v) => update('push_tags', v)}
        />

        <SwitchField
          id="sync-poll-enabled"
          label={__('Suppression polling', 'wp-sms')}
          description={__('Poll for unsubscribes, bounces, and complaints.', 'wp-sms')}
          checked={settings.poll_enabled}
          onCheckedChange={(v) => update('poll_enabled', v)}
        />

        {settings.poll_enabled && (
          <div className="space-y-1.5 ps-4">
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

        <SwitchField
          id="sync-remove-on-delete"
          label={__('Remove on delete', 'wp-sms')}
          description={__('Remove contact from provider when deleted locally.', 'wp-sms')}
          checked={settings.remove_on_delete}
          onCheckedChange={(v) => update('remove_on_delete', v)}
        />

        <Button size="sm" onClick={handleSave} disabled={saving}>
          {saving && <Loader2 className="me-2 h-4 w-4 animate-spin" />}
          Save Settings
        </Button>
      </div>
    </PageSection>
  );
}
