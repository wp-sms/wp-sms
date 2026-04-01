import { __, _n, sprintf } from '@wordpress/i18n';
import { useState } from 'react';
import { useWebhooks } from '@/hooks/use-webhooks';
import type { OutboundWebhook, WebhookEvent, WebhookEventGroups } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { PageHeader } from '@/components/layout/page-header';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { NameCell } from '@/components/ui/name-cell';
import { ActionsCell } from '@/components/ui/actions-cell';
import {
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
  Plus, Webhook, Pencil, Trash2, ArrowLeft,
  Check, X, Minus, Loader2, Copy, ChevronDown, RefreshCw, Zap,
} from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';
import { copyToClipboard } from '@/lib/utils';
import { Card, CardContent } from '@/components/ui/card';

type View =
  | { mode: 'list' }
  | { mode: 'create' }
  | { mode: 'edit'; webhook: OutboundWebhook };

function HealthIndicator({ lastTest }: { lastTest: OutboundWebhook['last_test'] }) {
  if (!lastTest) return <Minus className="h-4 w-4 text-muted-foreground" />;
  return lastTest.success
    ? <Check className="h-4 w-4 text-success" />
    : <X className="h-4 w-4 text-destructive" />;
}

function truncateUrl(url: string, maxLen = 40): string {
  if (url.length <= maxLen) return url;
  return url.slice(0, maxLen) + '\u2026';
}

export function Webhooks({ embedded }: { embedded?: boolean } = {}) {
  const {
    webhooks, loading, eventGroups, eventsLoading,
    createWebhook, updateWebhook, deleteWebhook, toggleWebhook, testConnection,
    refetch,
  } = useWebhooks();
  const [view, setView] = useState<View>({ mode: 'list' });
  const [deleting, setDeleting] = useState<string | null>(null);
  const [testing, setTesting] = useState<string | null>(null);
  const confirm = useConfirm();

  const handleDelete = async (id: string) => {
    const ok = await confirm({
      title: __('Delete webhook?', 'wp-sms'),
      description: __('This webhook will be permanently removed. Pending deliveries will be skipped.', 'wp-sms'),
      confirmLabel: __('Delete', 'wp-sms'),
      variant: 'destructive',
    });
    if (!ok) return;
    setDeleting(id);
    try {
      await deleteWebhook(id);
      toast.success(__('Webhook deleted.', 'wp-sms'));
    } catch {
      toast.error(__('Failed to delete webhook.', 'wp-sms'));
    } finally {
      setDeleting(null);
    }
  };

  const handleTest = async (id: string) => {
    setTesting(id);
    try {
      const result = await testConnection(id);
      if (result.success) {
        toast.success(result.message);
      } else {
        toast.error(result.message);
      }
    } catch {
      toast.error(__('Failed to test connection.', 'wp-sms'));
    } finally {
      setTesting(null);
    }
  };

  const handleToggle = async (id: string) => {
    try {
      await toggleWebhook(id);
    } catch {
      toast.error(__('Failed to toggle webhook.', 'wp-sms'));
    }
  };

  if (view.mode === 'create') {
    return (
      <WebhookForm
        eventGroups={eventGroups}
        eventsLoading={eventsLoading}
        onSave={async (data) => {
          await createWebhook(data);
          toast.success(__('Webhook created.', 'wp-sms'));
          setView({ mode: 'list' });
        }}
        onBack={() => { setView({ mode: 'list' }); refetch(); }}
      />
    );
  }

  if (view.mode === 'edit') {
    return (
      <WebhookForm
        webhook={view.webhook}
        eventGroups={eventGroups}
        eventsLoading={eventsLoading}
        onSave={async (data) => {
          const updated = await updateWebhook(view.webhook.id, data);
          toast.success(__('Webhook updated.', 'wp-sms'));
          setView({ mode: 'edit', webhook: updated });
        }}
        onTest={() => handleTest(view.webhook.id)}
        testing={testing === view.webhook.id}
        onBack={() => { setView({ mode: 'list' }); refetch(); }}
      />
    );
  }

  return (
    <div className="space-y-4">
      {!embedded && (
        <PageHeader
          icon={Webhook}
          title={__('Webhooks', 'wp-sms')}
          metadata={sprintf(_n('%d webhook', '%d webhooks', webhooks.length, 'wp-sms'), webhooks.length)}
          actions={
            <Button size="sm" onClick={() => setView({ mode: 'create' })}>
              <Plus className="me-1.5 h-3.5 w-3.5" />
              {__('Create Webhook', 'wp-sms')}
            </Button>
          }
        />
      )}

      <DataTable
        loading={loading}
        isEmpty={webhooks.length === 0}
        empty={
          <EmptyState
            icon={Webhook}
            title={__('No webhooks yet', 'wp-sms')}
            description={__('Create your first webhook to push events to external services like Zapier, Make.com, or n8n.', 'wp-sms')}
            action={
              <Button size="sm" onClick={() => setView({ mode: 'create' })}>
                <Plus className="me-1.5 h-3.5 w-3.5" />
                {__('Create Webhook', 'wp-sms')}
              </Button>
            }
          />
        }
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{__('Name', 'wp-sms')}</TableHead>
              <TableHead>{__('URL', 'wp-sms')}</TableHead>
              <TableHead>{__('Events', 'wp-sms')}</TableHead>
              <TableHead>{__('Status', 'wp-sms')}</TableHead>
              <TableHead>{__('Health', 'wp-sms')}</TableHead>
              <TableHead className="w-[70px]" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {webhooks.map((wh) => (
              <TableRow key={wh.id} className="even:bg-muted/30">
                <NameCell onClick={() => setView({ mode: 'edit', webhook: wh })}>
                  {wh.name}
                </NameCell>
                <TableCell>
                  <code className="text-xs text-muted-foreground">{truncateUrl(wh.url)}</code>
                </TableCell>
                <TableCell>
                  <Badge variant="outline">{sprintf(_n('%d event', '%d events', wh.events.length, 'wp-sms'), wh.events.length)}</Badge>
                </TableCell>
                <TableCell>
                  <Switch
                    size="sm"
                    checked={wh.status === 'active'}
                    onCheckedChange={() => void handleToggle(wh.id)}
                  />
                </TableCell>
                <TableCell>
                  <HealthIndicator lastTest={wh.last_test} />
                </TableCell>
                <ActionsCell>
                  <DropdownMenuItem onClick={() => setView({ mode: 'edit', webhook: wh })}>
                    <Pencil className="h-4 w-4 me-2" />
                    {__('Edit', 'wp-sms')}
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => void handleTest(wh.id)}
                    disabled={testing === wh.id}
                  >
                    <Zap className="h-4 w-4 me-2" />
                    {__('Test Connection', 'wp-sms')}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => void handleDelete(wh.id)}
                    disabled={deleting === wh.id}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4 me-2" />
                    {__('Delete', 'wp-sms')}
                  </DropdownMenuItem>
                </ActionsCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}

interface WebhookFormProps {
  webhook?: OutboundWebhook;
  eventGroups: WebhookEventGroups | null;
  eventsLoading: boolean;
  onSave: (data: Record<string, unknown>) => Promise<void>;
  onBack: () => void;
  onTest?: () => void;
  testing?: boolean;
}

function WebhookForm({ webhook, eventGroups, eventsLoading, onSave, onBack, onTest, testing }: WebhookFormProps) {
  const [name, setName] = useState(webhook?.name ?? '');
  const [url, setUrl] = useState(webhook?.url ?? '');
  const [description, setDescription] = useState(webhook?.description ?? '');
  const [selectedEvents, setSelectedEvents] = useState<Set<string>>(new Set(webhook?.events ?? []));
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [previewEvent, setPreviewEvent] = useState<string | null>(null);
  const confirm = useConfirm();

  const isEdit = !!webhook;

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    if (!name.trim()) errs.name = __('Name is required.', 'wp-sms');
    if (!url.trim()) errs.url = __('URL is required.', 'wp-sms');
    else {
      try {
        new URL(url);
      } catch {
        errs.url = __('Enter a valid URL.', 'wp-sms');
      }
    }
    if (selectedEvents.size === 0) errs.events = __('Select at least one event.', 'wp-sms');
    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) return;
    setSaving(true);
    try {
      await onSave({
        name: name.trim(),
        url: url.trim(),
        events: Array.from(selectedEvents),
        description: description.trim() || null,
      });
      setErrors({});
    } catch (e: unknown) {
      const apiErr = e as { errors?: Record<string, string> };
      if (apiErr?.errors) {
        setErrors(apiErr.errors);
      } else {
        toast.error(__('Failed to save webhook.', 'wp-sms'));
      }
    } finally {
      setSaving(false);
    }
  };

  const handleRegenerateSecret = async () => {
    if (!webhook) return;
    const ok = await confirm({
      title: __('Regenerate secret?', 'wp-sms'),
      description: __('In-flight deliveries signed with the old secret will fail verification on the receiving end.', 'wp-sms'),
      confirmLabel: __('Regenerate', 'wp-sms'),
      variant: 'destructive',
    });
    if (!ok) return;
    try {
      await onSave({ regenerate_secret: true });
      toast.success(__('Secret regenerated.', 'wp-sms'));
    } catch {
      toast.error(__('Failed to regenerate secret.', 'wp-sms'));
    }
  };

  const toggleEvent = (name: string) => {
    setSelectedEvents((prev) => {
      const next = new Set(prev);
      if (next.has(name)) next.delete(name);
      else next.add(name);
      return next;
    });
  };

  const toggleGroup = (events: WebhookEvent[]) => {
    const names = events.map((e) => e.name);
    const allSelected = names.every((n) => selectedEvents.has(n));
    setSelectedEvents((prev) => {
      const next = new Set(prev);
      for (const n of names) {
        if (allSelected) next.delete(n);
        else next.add(n);
      }
      return next;
    });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={onBack}>
          <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
          {__('Back', 'wp-sms')}
        </Button>
        <h2 className="text-lg font-semibold">{isEdit ? __('Edit Webhook', 'wp-sms') : __('Create Webhook', 'wp-sms')}</h2>
      </div>

      <Card>
        <CardContent className="pt-6">
        <div className="max-w-2xl space-y-4">
        <div className="space-y-2">
          <Label htmlFor="wh-name">{__('Name', 'wp-sms')} <span className="text-destructive">*</span></Label>
          <Input
            id="wh-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. Zapier Integration"
          />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="wh-url">{__('URL', 'wp-sms')} <span className="text-destructive">*</span></Label>
          <Input
            id="wh-url"
            dir="ltr"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="https://hooks.zapier.com/..."
          />
          <p className="text-xs text-muted-foreground">
            {__('Paste the webhook URL from Zapier, Make.com, n8n, or any service that accepts webhooks.', 'wp-sms')}
          </p>
          {errors.url && <p className="text-sm text-destructive">{errors.url}</p>}
        </div>

        <div className="space-y-2">
          <Label>{__('Events', 'wp-sms')} <span className="text-destructive">*</span></Label>
          {eventsLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground py-4">
              <Loader2 className="h-4 w-4 animate-spin" /> {__('Loading events...', 'wp-sms')}
            </div>
          ) : eventGroups ? (
            <div className="border rounded-lg divide-y">
              {Object.entries(eventGroups).map(([group, events]) => {
                const groupNames = events.map((e) => e.name);
                const allSelected = groupNames.every((n) => selectedEvents.has(n));
                const someSelected = groupNames.some((n) => selectedEvents.has(n));

                return (
                  <Collapsible key={group} defaultOpen>
                    <div className="flex items-center justify-between px-3 py-2 bg-muted/40">
                      <div className="flex items-center gap-2">
                        <Checkbox
                          checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                          onCheckedChange={() => toggleGroup(events)}
                        />
                        <CollapsibleTrigger className="flex items-center gap-1.5 text-sm font-medium hover:text-primary transition-colors">
                          {group}
                          <ChevronDown className="h-3.5 w-3.5" />
                        </CollapsibleTrigger>
                      </div>
                      <span className="text-xs text-muted-foreground">
                        {groupNames.filter((n) => selectedEvents.has(n)).length}/{events.length}
                      </span>
                    </div>
                    <CollapsibleContent>
                      <div className="divide-y">
                        {events.map((evt) => (
                          <div key={evt.name} className="px-3 py-2 ps-8">
                            <div className="flex items-start gap-2">
                              <Checkbox
                                checked={selectedEvents.has(evt.name)}
                                onCheckedChange={() => toggleEvent(evt.name)}
                                className="mt-0.5"
                              />
                              <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2">
                                  <span className="text-sm font-medium">{evt.label}</span>
                                  <button
                                    type="button"
                                    className="text-xs text-muted-foreground hover:text-primary transition-colors"
                                    onClick={() => setPreviewEvent(previewEvent === evt.name ? null : evt.name)}
                                  >
                                    {previewEvent === evt.name ? __('Hide payload', 'wp-sms') : __('Preview payload', 'wp-sms')}
                                  </button>
                                </div>
                                <p className="text-xs text-muted-foreground mt-0.5">{evt.description}</p>
                                {previewEvent === evt.name && (
                                  <pre className="mt-2 p-2 bg-muted rounded text-xs overflow-x-auto">
                                    {JSON.stringify(evt.sample_payload, null, 2)}
                                  </pre>
                                )}
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </CollapsibleContent>
                  </Collapsible>
                );
              })}
            </div>
          ) : null}
          {errors.events && <p className="text-sm text-destructive">{errors.events}</p>}
        </div>

        {isEdit && webhook && (
          <div className="space-y-2">
            <Label>{__('Secret', 'wp-sms')}</Label>
            <div className="flex items-center gap-2">
              <Input
                dir="ltr"
                value={webhook.secret}
                readOnly
                className="font-mono text-xs"
              />
              <Button
                variant="outline"
                size="icon"
                className="shrink-0"
                aria-label={__('Copy secret', 'wp-sms')}
                onClick={async () => {
                  await copyToClipboard(webhook.secret);
                  toast.success(__('Secret copied to clipboard.', 'wp-sms'));
                }}
              >
                <Copy className="h-4 w-4" />
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="shrink-0"
                onClick={() => void handleRegenerateSecret()}
              >
                <RefreshCw className="h-3.5 w-3.5 me-1.5" />
                {__('Regenerate', 'wp-sms')}
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              {__('Use this secret to verify webhook signatures. Include it in your Zapier/Make configuration.', 'wp-sms')}
            </p>
          </div>
        )}

        <div className="space-y-2">
          <Label htmlFor="wh-desc">{__('Description (optional)', 'wp-sms')}</Label>
          <Textarea
            id="wh-desc"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder={__('What this webhook is used for...', 'wp-sms')}
            rows={2}
          />
        </div>

        <div className="flex items-center gap-3 pt-2">
          <Button onClick={() => void handleSubmit()} disabled={saving}>
            {saving && <Loader2 className="h-4 w-4 me-1.5 animate-spin" />}
            {isEdit ? __('Save Changes', 'wp-sms') : __('Create Webhook', 'wp-sms')}
          </Button>
          {isEdit && onTest && (
            <Button variant="outline" onClick={onTest} disabled={testing}>
              {testing ? <Loader2 className="h-4 w-4 me-1.5 animate-spin" /> : <Zap className="h-4 w-4 me-1.5" />}
              {__('Test Connection', 'wp-sms')}
            </Button>
          )}
          <Button variant="ghost" onClick={onBack}>{__('Cancel', 'wp-sms')}</Button>
        </div>
      </div>
        </CardContent>
      </Card>
    </div>
  );
}
