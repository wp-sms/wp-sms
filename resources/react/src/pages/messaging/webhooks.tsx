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
import { copyToClipboard, pluralize } from '@/lib/utils';
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

export function Webhooks() {
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
      title: 'Delete webhook?',
      description: 'This webhook will be permanently removed. Pending deliveries will be skipped.',
      confirmLabel: 'Delete',
      variant: 'destructive',
    });
    if (!ok) return;
    setDeleting(id);
    try {
      await deleteWebhook(id);
      toast.success('Webhook deleted.');
    } catch {
      toast.error('Failed to delete webhook.');
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
      toast.error('Failed to test connection.');
    } finally {
      setTesting(null);
    }
  };

  const handleToggle = async (id: string) => {
    try {
      await toggleWebhook(id);
    } catch {
      toast.error('Failed to toggle webhook.');
    }
  };

  if (view.mode === 'create') {
    return (
      <WebhookForm
        eventGroups={eventGroups}
        eventsLoading={eventsLoading}
        onSave={async (data) => {
          await createWebhook(data);
          toast.success('Webhook created.');
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
          toast.success('Webhook updated.');
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
      <PageHeader
        icon={Webhook}
        title="Webhooks"
        metadata={pluralize(webhooks.length, 'webhook')}
        actions={
          <Button size="sm" onClick={() => setView({ mode: 'create' })}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            Create Webhook
          </Button>
        }
      />

      <DataTable
        loading={loading}
        isEmpty={webhooks.length === 0}
        empty={
          <EmptyState
            icon={Webhook}
            title="No webhooks yet"
            description="Create your first webhook to push events to external services like Zapier, Make.com, or n8n."
            action={
              <Button size="sm" onClick={() => setView({ mode: 'create' })}>
                <Plus className="mr-1.5 h-3.5 w-3.5" />
                Create Webhook
              </Button>
            }
          />
        }
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>URL</TableHead>
              <TableHead>Events</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Health</TableHead>
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
                  <Badge variant="outline">{pluralize(wh.events.length, 'event')}</Badge>
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
                    <Pencil className="h-4 w-4 mr-2" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => void handleTest(wh.id)}
                    disabled={testing === wh.id}
                  >
                    <Zap className="h-4 w-4 mr-2" />
                    Test Connection
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => void handleDelete(wh.id)}
                    disabled={deleting === wh.id}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete
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
    if (!name.trim()) errs.name = 'Name is required.';
    if (!url.trim()) errs.url = 'URL is required.';
    else {
      try {
        new URL(url);
      } catch {
        errs.url = 'Enter a valid URL.';
      }
    }
    if (selectedEvents.size === 0) errs.events = 'Select at least one event.';
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
        toast.error('Failed to save webhook.');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleRegenerateSecret = async () => {
    if (!webhook) return;
    const ok = await confirm({
      title: 'Regenerate secret?',
      description: 'In-flight deliveries signed with the old secret will fail verification on the receiving end.',
      confirmLabel: 'Regenerate',
      variant: 'destructive',
    });
    if (!ok) return;
    try {
      await onSave({ regenerate_secret: true });
      toast.success('Secret regenerated.');
    } catch {
      toast.error('Failed to regenerate secret.');
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
          <ArrowLeft className="mr-1 h-4 w-4" />
          Back
        </Button>
        <h2 className="text-lg font-semibold">{isEdit ? 'Edit Webhook' : 'Create Webhook'}</h2>
      </div>

      <Card>
        <CardContent className="pt-6">
        <div className="max-w-2xl space-y-4">
        <div className="space-y-2">
          <Label htmlFor="wh-name">Name</Label>
          <Input
            id="wh-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. Zapier Integration"
          />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="wh-url">URL</Label>
          <Input
            id="wh-url"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="https://hooks.zapier.com/..."
          />
          <p className="text-xs text-muted-foreground">
            Paste the webhook URL from Zapier, Make.com, n8n, or any service that accepts webhooks.
          </p>
          {errors.url && <p className="text-sm text-destructive">{errors.url}</p>}
        </div>

        <div className="space-y-2">
          <Label>Events</Label>
          {eventsLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground py-4">
              <Loader2 className="h-4 w-4 animate-spin" /> Loading events...
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
                          <div key={evt.name} className="px-3 py-2 pl-8">
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
                                    {previewEvent === evt.name ? 'Hide payload' : 'Preview payload'}
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
            <Label>Secret</Label>
            <div className="flex items-center gap-2">
              <Input
                value={webhook.secret}
                readOnly
                className="font-mono text-xs"
              />
              <Button
                variant="outline"
                size="icon"
                className="shrink-0"
                onClick={async () => {
                  await copyToClipboard(webhook.secret);
                  toast.success('Secret copied to clipboard.');
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
                <RefreshCw className="h-3.5 w-3.5 mr-1.5" />
                Regenerate
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Use this secret to verify webhook signatures. Include it in your Zapier/Make configuration.
            </p>
          </div>
        )}

        <div className="space-y-2">
          <Label htmlFor="wh-desc">Description (optional)</Label>
          <Textarea
            id="wh-desc"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="What this webhook is used for..."
            rows={2}
          />
        </div>

        <div className="flex items-center gap-3 pt-2">
          <Button onClick={() => void handleSubmit()} disabled={saving}>
            {saving && <Loader2 className="h-4 w-4 mr-1.5 animate-spin" />}
            {isEdit ? 'Save Changes' : 'Create Webhook'}
          </Button>
          {isEdit && onTest && (
            <Button variant="outline" onClick={onTest} disabled={testing}>
              {testing ? <Loader2 className="h-4 w-4 mr-1.5 animate-spin" /> : <Zap className="h-4 w-4 mr-1.5" />}
              Test Connection
            </Button>
          )}
          <Button variant="ghost" onClick={onBack}>Cancel</Button>
        </div>
      </div>
        </CardContent>
      </Card>
    </div>
  );
}
