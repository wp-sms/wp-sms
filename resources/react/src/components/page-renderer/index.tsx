import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback, useRef } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { SchemaForm } from '@/components/schema-form';
import { useConfirm } from '@/components/confirm-provider';
import { DataTable } from '@/components/ui/data-table';
import { api } from '@/lib/api';
import type { LayoutNode, JsonSchema } from '@/lib/api';
import { isAbortError, getErrorMessage } from '@/lib/error-utils';
import {
  CheckCircle2,
  XCircle,
  Loader2,
  Search,
  AlertCircle,
  Info,
  AlertTriangle,
  TrendingUp,
  TrendingDown,
} from 'lucide-react';

interface PageRendererProps {
  layout: LayoutNode[];
  title?: string;
}

export function PageRenderer({ layout, title }: PageRendererProps) {
  return (
    <div className="space-y-6">
      {title && <h2 className="text-lg font-semibold">{title}</h2>}
      {layout.map((node, i) => (
        <BlockRenderer key={i} node={node} />
      ))}
    </div>
  );
}

function BlockRenderer({ node }: { node: LayoutNode }) {
  switch (node.component) {
    case 'form':
      return <FormBlock endpoint={node.endpoint!} />;
    case 'table':
      return <TableBlock endpoint={node.endpoint!} columns={node.columns ?? []} searchable={node.searchable} actions={node.actions} />;
    case 'tabs':
      return <TabsBlock children={node.children ?? []} />;
    case 'stats':
      return <StatsBlock endpoint={node.endpoint!} />;
    case 'status':
      return <StatusBlock endpoint={node.endpoint!} testEndpoint={node.test_endpoint} />;
    case 'alert':
      return <AlertBlock variant={node.variant} message={node.message ?? ''} />;
    case 'text':
      return <TextBlock content={node.content ?? ''} />;
    case 'actions':
      return <ActionsBlock buttons={node.buttons ?? []} />;
    default:
      return null;
  }
}

function FormBlock({ endpoint }: { endpoint: string }) {
  const [schema, setSchema] = useState<JsonSchema | null>(null);
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  const [dirty, setDirty] = useState(false);
  const savedTimerRef = useRef<ReturnType<typeof setTimeout>>();

  useEffect(() => {
    return () => { clearTimeout(savedTimerRef.current); };
  }, []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    api.get<{ schema: JsonSchema; values: Record<string, unknown> }>(endpoint)
      .then(data => {
        if (cancelled) return;
        setSchema(data.schema);
        setValues(data.values);
      })
      .catch(e => { if (!isAbortError(e)) setError(getErrorMessage(e, __('Failed to load', 'wp-sms'))); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [endpoint]);

  const handleSave = useCallback(async () => {
    setSaving(true);
    setSaved(false);
    setError('');
    try {
      const res = await api.put<{ success: boolean; values: Record<string, unknown> }>(endpoint, values);
      setValues(res.values);
      setDirty(false);
      setSaved(true);
      clearTimeout(savedTimerRef.current);
      savedTimerRef.current = setTimeout(() => setSaved(false), 2000);
    } catch (e: unknown) {
      setError(getErrorMessage(e, __('Save failed', 'wp-sms')));
    } finally {
      setSaving(false);
    }
  }, [endpoint, values]);

  if (loading) return <Skeleton className="h-48 w-full" />;
  if (error && !schema) return <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{error}</AlertDescription></Alert>;
  if (!schema) return null;

  return (
    <Card>
      <CardContent className="pt-6 space-y-4">
        <SchemaForm
          schema={schema}
          values={values}
          onChange={(v) => { setValues(v); setDirty(true); }}
        />
        {error && <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{error}</AlertDescription></Alert>}
        <div className="flex items-center gap-2">
          <Button onClick={handleSave} disabled={saving || !dirty}>
            {saving ? <><Loader2 className="h-4 w-4 animate-spin mr-2" />{__('Saving…', 'wp-sms')}</> : __('Save', 'wp-sms')}
          </Button>
          {saved && <span className="text-sm text-success">{__('Saved', 'wp-sms')}</span>}
        </div>
      </CardContent>
    </Card>
  );
}

interface TableColumn {
  key: string;
  label: string;
}

interface RowAction {
  label: string;
  endpoint: string;
  method: string;
  confirm?: string;
}

function TableBlock({
  endpoint,
  columns,
  searchable,
  actions,
}: {
  endpoint: string;
  columns: TableColumn[];
  searchable?: boolean;
  actions?: RowAction[];
}) {
  const [items, setItems] = useState<Record<string, unknown>[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const confirm = useConfirm();

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
      if (debouncedSearch) params.set('search', debouncedSearch);
      const data = await api.get<{ items: Record<string, unknown>[]; total: number; page: number; per_page: number }>(`${endpoint}?${params}`);
      setItems(data.items);
      setTotal(data.total);
    } catch (e: unknown) {
      if (!isAbortError(e)) setError(getErrorMessage(e, __('Failed to load', 'wp-sms')));
    } finally {
      setLoading(false);
    }
  }, [endpoint, page, perPage, debouncedSearch]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleAction = useCallback(async (action: RowAction, row: Record<string, unknown>) => {
    if (action.confirm) {
      const ok = await confirm({ title: action.confirm, variant: action.method === 'DELETE' ? 'destructive' : 'default' });
      if (!ok) return;
    }
    const url = action.endpoint.replace(/\{(\w+)\}/g, (_, key) => String(row[key] ?? ''));
    try {
      if (action.method === 'DELETE') {
        await api.del(url);
      } else {
        await api.post(url, {});
      }
      fetchData();
    } catch (e: unknown) {
      setError(getErrorMessage(e, __('Action failed', 'wp-sms')));
    }
  }, [confirm, fetchData]);

  const totalPages = Math.max(1, Math.ceil(total / perPage));

  return (
    <Card>
      <CardContent className="pt-6 space-y-4">
        {searchable && (
          <div className="relative max-w-sm">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder={__('Search…', 'wp-sms')}
              className="pl-9"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
        )}
        {error && <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{error}</AlertDescription></Alert>}
        <DataTable
          loading={loading}
          isEmpty={!loading && items.length === 0}
          empty={<p className="py-8 text-center text-sm text-muted-foreground">{__('No items found.', 'wp-sms')}</p>}
          pagination={totalPages > 1 ? { page, totalPages, onPageChange: setPage } : undefined}
        >
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50">
                {columns.map(col => (
                  <th key={col.key} className="px-4 py-2.5 text-left font-medium text-muted-foreground">{col.label}</th>
                ))}
                {actions && actions.length > 0 && <th className="px-4 py-2.5 text-right font-medium text-muted-foreground">{__('Actions', 'wp-sms')}</th>}
              </tr>
            </thead>
            <tbody>
              {items.map((row, i) => (
                <tr key={i} className="border-b last:border-0">
                  {columns.map(col => (
                    <td key={col.key} className="px-4 py-2.5">{String(row[col.key] ?? '')}</td>
                  ))}
                  {actions && actions.length > 0 && (
                    <td className="px-4 py-2.5 text-right">
                      <div className="flex justify-end gap-1">
                        {actions.map((action, ai) => (
                          <Button key={ai} variant="ghost" size="sm" onClick={() => handleAction(action, row)}>
                            {action.label}
                          </Button>
                        ))}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </DataTable>
      </CardContent>
    </Card>
  );
}

function TabsBlock({ children }: { children: (LayoutNode & { label?: string })[] }) {
  const tabs = children.filter(c => c.label);
  if (tabs.length === 0) return null;

  return (
    <Tabs defaultValue={tabs[0].label}>
      <TabsList>
        {tabs.map(tab => (
          <TabsTrigger key={tab.label} value={tab.label!}>{tab.label}</TabsTrigger>
        ))}
      </TabsList>
      {tabs.map(tab => (
        <TabsContent key={tab.label} value={tab.label!}>
          <div className="space-y-6">
            {(tab.children ?? []).map((child, i) => (
              <BlockRenderer key={i} node={child} />
            ))}
          </div>
        </TabsContent>
      ))}
    </Tabs>
  );
}

interface Stat {
  label: string;
  value: string | number;
  change?: string;
  description?: string;
}

function StatsBlock({ endpoint }: { endpoint: string }) {
  const [stats, setStats] = useState<Stat[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    api.get<{ stats: Stat[] }>(endpoint)
      .then(data => { if (!cancelled) setStats(data.stats); })
      .catch(() => {})
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [endpoint]);

  if (loading) return <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-24" />)}</div>;

  return (
    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
      {stats.map((stat, i) => (
        <Card key={i}>
          <CardContent className="pt-4 pb-4">
            <p className="text-xs text-muted-foreground font-medium">{stat.label}</p>
            <div className="flex items-baseline gap-2 mt-1">
              <p className="text-2xl font-bold">{stat.value}</p>
              {stat.change && (
                <span className={`flex items-center text-xs font-medium ${stat.change.startsWith('-') ? 'text-destructive' : 'text-success'}`}>
                  {stat.change.startsWith('-') ? <TrendingDown className="h-3 w-3 mr-0.5" /> : <TrendingUp className="h-3 w-3 mr-0.5" />}
                  {stat.change}
                </span>
              )}
            </div>
            {stat.description && <p className="text-xs text-muted-foreground mt-1">{stat.description}</p>}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

function StatusBlock({ endpoint, testEndpoint }: { endpoint: string; testEndpoint?: string }) {
  const [connected, setConnected] = useState<boolean | null>(null);
  const [message, setMessage] = useState('');
  const [details, setDetails] = useState('');
  const [loading, setLoading] = useState(true);
  const [testing, setTesting] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api.get<{ connected: boolean; message: string; details?: string }>(endpoint)
      .then(data => {
        if (cancelled) return;
        setConnected(data.connected);
        setMessage(data.message);
        setDetails(data.details ?? '');
      })
      .catch(() => { if (!cancelled) { setConnected(false); setMessage(__('Failed to check status', 'wp-sms')); } })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [endpoint]);

  const handleTest = useCallback(async () => {
    if (!testEndpoint) return;
    setTesting(true);
    try {
      const res = await api.post<{ success: boolean; message: string }>(testEndpoint, {});
      setConnected(res.success);
      setMessage(res.message);
    } catch (e: unknown) {
      setConnected(false);
      setMessage(getErrorMessage(e, __('Test failed', 'wp-sms')));
    } finally {
      setTesting(false);
    }
  }, [testEndpoint]);

  if (loading) return <Skeleton className="h-20 w-full" />;

  return (
    <Card>
      <CardContent className="pt-4 pb-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            {connected
              ? <CheckCircle2 className="h-5 w-5 text-success" />
              : <XCircle className="h-5 w-5 text-destructive" />}
            <div>
              <p className="text-sm font-medium">{connected ? __('Connected', 'wp-sms') : __('Disconnected', 'wp-sms')}</p>
              {message && <p className="text-xs text-muted-foreground">{message}</p>}
              {details && <p className="text-xs text-muted-foreground mt-0.5">{details}</p>}
            </div>
          </div>
          {testEndpoint && (
            <Button variant="outline" size="sm" onClick={handleTest} disabled={testing}>
              {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : __('Test Connection', 'wp-sms')}
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

function AlertBlock({ variant, message }: { variant?: string; message: string }) {
  const alertVariant = (variant === 'destructive' || variant === 'warning' || variant === 'success' || variant === 'info')
    ? variant
    : 'default';

  const IconComponent = alertVariant === 'destructive' ? AlertCircle
    : alertVariant === 'warning' ? AlertTriangle
    : alertVariant === 'info' ? Info
    : alertVariant === 'success' ? CheckCircle2
    : null;

  return (
    <Alert variant={alertVariant}>
      {IconComponent && <IconComponent className="h-4 w-4" />}
      <AlertDescription>{message}</AlertDescription>
    </Alert>
  );
}

function TextBlock({ content }: { content: string }) {
  return <p className="text-sm text-muted-foreground">{content}</p>;
}

function ActionsBlock({ buttons }: { buttons: { label: string; endpoint: string; method: string; variant?: string; confirm?: string }[] }) {
  const confirm = useConfirm();
  const [loadingIdx, setLoadingIdx] = useState<number | null>(null);
  const [error, setError] = useState('');

  const handleClick = useCallback(async (btn: typeof buttons[0], idx: number) => {
    if (btn.confirm) {
      const ok = await confirm({ title: btn.confirm, variant: btn.method === 'DELETE' ? 'destructive' : 'default' });
      if (!ok) return;
    }
    setLoadingIdx(idx);
    setError('');
    try {
      if (btn.method === 'DELETE') {
        await api.del(btn.endpoint);
      } else {
        await api.post(btn.endpoint, {});
      }
    } catch (e: unknown) {
      setError(getErrorMessage(e, __('Action failed', 'wp-sms')));
    } finally {
      setLoadingIdx(null);
    }
  }, [confirm]);

  return (
    <div className="space-y-2">
      <div className="flex gap-2 flex-wrap">
        {buttons.map((btn, i) => (
          <Button
            key={i}
            variant={btn.variant === 'destructive' ? 'destructive' : btn.variant === 'outline' ? 'outline' : 'default'}
            onClick={() => handleClick(btn, i)}
            disabled={loadingIdx !== null}
          >
            {loadingIdx === i && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
            {btn.label}
          </Button>
        ))}
      </div>
      {error && <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{error}</AlertDescription></Alert>}
    </div>
  );
}
