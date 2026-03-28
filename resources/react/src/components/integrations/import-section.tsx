import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useCallback, useRef } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Skeleton } from '@/components/ui/skeleton';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Progress } from '@/components/ui/progress';
import { Loader2, RefreshCw, Download } from 'lucide-react';
import { toast } from 'sonner';
import { formatDateTime } from '@/lib/format';
import { FieldMappingTable } from '@/components/integrations/field-mapping-table';
import { useImportFields, saveImportSettings, startImport, getImportStatus } from '@/hooks/use-integrations';
import { api, getConfig } from '@/lib/api';
import type { ImportSettings, ImportStats, ProviderList } from '@/lib/api';

interface ImportSectionProps {
  integrationId: string;
  importSettings?: ImportSettings | null;
  importStats?: ImportStats | null;
  onUpdate: () => void;
}

export function ImportSection({ integrationId, importSettings, importStats, onUpdate }: ImportSectionProps) {
  const [enabled, setEnabled] = useState(importSettings?.enabled ?? false);
  const { fields: importFields, loading: fieldsLoading } = useImportFields(enabled ? integrationId : null);

  const [mapping, setMapping] = useState<Record<string, string>>(importSettings?.field_mapping ?? {});
  const [autoSync, setAutoSync] = useState(importSettings?.auto_sync ?? false);
  const [roles, setRoles] = useState<string[]>(importSettings?.roles ?? []);
  const [listId, setListId] = useState(importSettings?.list_id ?? '');
  const [lists, setLists] = useState<ProviderList[]>([]);
  const [loadingLists, setLoadingLists] = useState(false);
  const [saving, setSaving] = useState(false);
  const [importing, setImporting] = useState(false);
  const [progress, setProgress] = useState<{ synced: number } | null>(null);
  const [totalAvailable, setTotalAvailable] = useState(0);
  const [toggling, setToggling] = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval>>();

  const isWpUsers = integrationId === 'wordpress';
  const hasListPicker = importFields?.config_schema?.list_id != null;
  const allRoles = getConfig().roles;
  const isImporting = importStats?.sync_in_progress || importing;

  // Initialize from importSettings
  useEffect(() => {
    if (importSettings) {
      setEnabled(importSettings.enabled ?? false);
      setMapping(importSettings.field_mapping ?? {});
      setAutoSync(importSettings.auto_sync ?? false);
      setRoles(importSettings.roles ?? []);
      setListId(importSettings.list_id ?? '');
    }
  }, [importSettings]);

  // Initialize defaults from import fields
  useEffect(() => {
    if (importFields && !importSettings?.field_mapping) {
      setMapping(importFields.default_mapping);
    }
  }, [importFields, importSettings?.field_mapping]);

  // Fetch lists for list-based integrations
  useEffect(() => {
    if (!hasListPicker) return;
    setLoadingLists(true);
    api.get<{ lists: ProviderList[] }>(`integrations/${integrationId}/lists`)
      .then((res) => setLists(res.lists))
      .catch(() => setLists([]))
      .finally(() => setLoadingLists(false));
  }, [integrationId, hasListPicker]);

  // If import already in progress on mount, start polling
  useEffect(() => {
    if (importStats?.sync_in_progress) {
      setImporting(true);
      setProgress(importStats.sync_progress);
      startPolling();
    }
    return () => stopPolling();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const startPolling = useCallback(() => {
    stopPolling();
    pollRef.current = setInterval(async () => {
      try {
        const status = await getImportStatus(integrationId);
        setProgress((prev) => {
          const next = status.import_stats.sync_progress;
          return next?.synced === prev?.synced ? prev : next;
        });
        if (!status.import_stats.sync_in_progress) {
          stopPolling();
          setImporting(false);
          setProgress(null);
          onUpdate();
          toast.success(sprintf(__('Import complete: %s contacts imported', 'wp-sms'), status.import_stats.total_synced));
        }
      } catch {
        // Keep polling on error
      }
    }, 3000);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [integrationId, onUpdate]);

  function stopPolling() {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = undefined;
    }
  }

  async function handleToggle(value: boolean) {
    setToggling(true);
    try {
      if (value) {
        setEnabled(true);
      } else {
        await saveImportSettings(integrationId, { enabled: false } as ImportSettings);
        setEnabled(false);
        toast.success(__('Contact import disabled', 'wp-sms'));
        onUpdate();
      }
    } catch {
      toast.error(__('Failed to update import settings', 'wp-sms'));
    } finally {
      setToggling(false);
    }
  }

  async function handleSave() {
    setSaving(true);
    try {
      const settings: ImportSettings = {
        enabled: true,
        field_mapping: mapping,
        auto_sync: autoSync,
        ...(isWpUsers ? { roles } : {}),
        ...(hasListPicker ? { list_id: listId } : {}),
      };
      await saveImportSettings(integrationId, settings);
      toast.success(__('Import settings saved', 'wp-sms'));
      onUpdate();
    } catch {
      toast.error(__('Failed to save import settings', 'wp-sms'));
    } finally {
      setSaving(false);
    }
  }

  async function handleImport() {
    setImporting(true);
    try {
      const res = await startImport(integrationId);
      if (res.total_available === 0) {
        toast.info(__('No contacts to import', 'wp-sms'));
        setImporting(false);
        return;
      }
      setTotalAvailable(res.total_available);
      setProgress({ synced: 0 });
      startPolling();
    } catch {
      toast.error(__('Failed to start import', 'wp-sms'));
      setImporting(false);
    }
  }

  function toggleRole(role: string) {
    setRoles((prev) =>
      prev.includes(role) ? prev.filter((r) => r !== role) : [...prev, role],
    );
  }

  if (enabled && fieldsLoading) {
    return (
      <Card>
        <CardHeader>
          <Skeleton className="h-5 w-40" />
          <Skeleton className="h-4 w-64 mt-1" />
        </CardHeader>
        <CardContent className="space-y-3">
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-base flex items-center gap-2">
              <Download className="h-4 w-4 text-blue-500" />
              {__('Contact Import', 'wp-sms')}
            </CardTitle>
            <CardDescription>
              {__('Import contacts from this source into your contact database.', 'wp-sms')}
            </CardDescription>
          </div>
          <Switch checked={enabled} onCheckedChange={handleToggle} disabled={toggling} />
        </div>
      </CardHeader>
      {enabled && <CardContent className="space-y-5">
        {/* Role selection for WordPress */}
        {isWpUsers && (
          <div className="space-y-2">
            <label className="text-sm font-medium">User Roles</label>
            <p className="text-xs text-muted-foreground">
              {__('Only import users with these roles. Leave empty to import all roles.', 'wp-sms')}
            </p>
            <div className="grid grid-cols-2 gap-2">
              {Object.entries(allRoles).map(([value, label]) => (
                <label key={value} className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={roles.includes(value)}
                    onCheckedChange={() => toggleRole(value)}
                  />
                  {label}
                </label>
              ))}
            </div>
          </div>
        )}

        {/* List picker for EO/Mailtrap */}
        {hasListPicker && (
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Import from List</label>
            {loadingLists ? (
              <Skeleton className="h-9 w-full" />
            ) : (
              <Select value={listId} onValueChange={setListId}>
                <SelectTrigger>
                  <SelectValue placeholder={__('Select a list...', 'wp-sms')} />
                </SelectTrigger>
                <SelectContent>
                  {lists.map((list) => (
                    <SelectItem key={list.id} value={String(list.id)}>
                      {list.name} ({list.contact_count})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>
        )}

        {/* Field mapping */}
        {importFields && (
          <div className="space-y-2">
            <label className="text-sm font-medium">Field Mapping</label>
            <FieldMappingTable
              mapping={mapping}
              availableFields={importFields.fields}
              onChange={setMapping}
            />
          </div>
        )}

        {/* Auto-sync toggle */}
        <div className="flex items-center justify-between">
          <div>
            <label className="text-sm font-medium">Auto-sync</label>
            <p className="text-xs text-muted-foreground">
              {isWpUsers
                ? 'Automatically sync when users register, update their profile, or are deleted.'
                : 'Automatically sync new contacts on a recurring schedule.'}
            </p>
          </div>
          <Switch checked={autoSync} onCheckedChange={setAutoSync} />
        </div>

        {/* Save button */}
        <Button size="sm" onClick={handleSave} disabled={saving}>
          {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
          Save Settings
        </Button>

        {/* Divider */}
        <hr className="border-border" />

        {/* Import status */}
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span className="text-muted-foreground">{__('Contacts imported', 'wp-sms')}</span>
              <p className="font-medium">{importStats?.total_synced ?? 0}</p>
            </div>
            <div>
              <span className="text-muted-foreground">{__('Last imported', 'wp-sms')}</span>
              <p className="font-medium">{importStats?.last_synced_at ? formatDateTime(importStats.last_synced_at) : 'Never'}</p>
            </div>
            {importStats?.last_error && (
              <div className="col-span-2">
                <span className="text-muted-foreground">{__('Last error', 'wp-sms')}</span>
                <p className="text-sm text-destructive">{importStats.last_error}</p>
              </div>
            )}
          </div>

          {/* Progress bar */}
          {isImporting && progress && (
            <div className="space-y-1.5">
              <div className="flex justify-between text-xs text-muted-foreground">
                <span>{__('Importing...', 'wp-sms')}</span>
                <span>{progress.synced} contacts{totalAvailable > 0 ? ` / ${totalAvailable}` : ''}</span>
              </div>
              <Progress value={totalAvailable > 0 ? Math.min(100, (progress.synced / totalAvailable) * 100) : 0} />
            </div>
          )}

          {/* Import button */}
          <Button variant="outline" size="sm" onClick={handleImport} disabled={isImporting}>
            {isImporting
              ? <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              : <RefreshCw className="mr-2 h-4 w-4" />}
            {isImporting ? 'Importing...' : 'Import Now'}
          </Button>
        </div>
      </CardContent>}
    </Card>
  );
}
