import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription } from '@/components/ui/drawer';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';
import { insertVariableAtCursor } from '@/lib/text-utils';
import type { FieldOption } from '@/lib/condition-utils';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DataTable } from '@/components/ui/data-table';
import { Mail, MessageSquare, Send, Smartphone, Eye, RotateCcw, Settings, RefreshCw, AlertTriangle, ExternalLink, FileText } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { pluralize } from '@/lib/utils';

interface VariableInfo {
  name: string;
  label: string;
  description: string;
  required: boolean;
  example: string;
}

interface ChannelContentData {
  body: string;
  subject?: string;
  cta?: string;
  cta_url?: string;
}

interface ChannelEditData {
  default: ChannelContentData | null;
  override: ChannelContentData | null;
  current: ChannelContentData | null;
}

interface TemplateMappingData {
  template_type: string;
  provider_template_id: string;
  gateway_id: string;
  language: string;
  variable_map: Record<string, string>;
  provider_template_name: string;
  provider_template_body: string;
  last_verified_at: number | null;
  source: 'catalog' | 'manual';
  regulatory_meta: Record<string, string>;
}

interface ProviderTemplateData {
  id: string;
  name: string;
  language: string;
  category: string;
  status: 'approved' | 'pending' | 'rejected' | 'paused' | 'disabled';
  body_text: string;
  variable_count: number;
  variables: Array<{ key: string; type: 'positional' | 'named'; label?: string }>;
  source: 'fetched' | 'manual';
}

interface TemplateCapabilities {
  supports_templates: boolean;
  fetchable: boolean;
  variable_style: 'positional' | 'named' | null;
  required_channels: string[];
}

interface ChannelTemplateInfo {
  gateway_id: string;
  mapping: TemplateMappingData | null;
  capabilities: TemplateCapabilities | null;
}

interface TemplateData {
  id: string;
  label: string;
  description: string;
  supported_channels: string[];
  visible_channels: string[];
  variables: Record<string, VariableInfo>;
  channels: Record<string, ChannelEditData>;
  toggleable: boolean;
  enabled: boolean;
  channel_template_info: Record<string, ChannelTemplateInfo>;
  /** @deprecated Use channel_template_info.whatsapp instead */
  whatsapp_gateway_id?: string;
  /** @deprecated Use channel_template_info.whatsapp instead */
  whatsapp_mapping?: TemplateMappingData | null;
}

interface PreviewData {
  subject: string | null;
  body: string;
  meta: Record<string, unknown>;
}

const CHANNEL_ICONS: Record<string, React.ReactNode> = {
  email: <Mail className="h-4 w-4" />,
  sms: <Smartphone className="h-4 w-4" />,
  whatsapp: <MessageSquare className="h-4 w-4" />,
  telegram: <Send className="h-4 w-4" />,
};

const CHANNEL_LABELS: Record<string, string> = {
  email: 'Email',
  sms: 'SMS',
  whatsapp: 'WhatsApp',
  telegram: 'Telegram',
};

function toFieldOptions(variables: Record<string, VariableInfo>): FieldOption[] {
  return Object.values(variables).map((v) => ({
    path: v.name,
    label: v.label,
    type: v.required ? 'required' : 'optional',
    example: v.example,
  }));
}

function PreviewDialog({
  preview,
  channel,
  templateLabel,
  onClose,
}: {
  preview: PreviewData;
  channel: string;
  templateLabel: string;
  onClose: () => void;
}) {
  const isEmail = channel === 'email';
  const channelLabel = CHANNEL_LABELS[channel] ?? channel;

  return (
    <Dialog open onOpenChange={(open) => { if (!open) onClose(); }}>
      <DialogContent className={`max-h-[85vh] flex flex-col gap-0 p-0 overflow-hidden ${isEmail ? 'max-w-3xl' : 'max-w-md'}`}>
        {/* Header bar */}
        <div className="flex items-center gap-3 border-b px-5 py-3.5">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted">
            {CHANNEL_ICONS[channel]}
          </div>
          <DialogHeader className="flex-1 min-w-0 gap-0">
            <DialogTitle className="text-sm font-medium">{templateLabel}</DialogTitle>
            <DialogDescription className="text-xs">
              {channelLabel} preview · example data
            </DialogDescription>
          </DialogHeader>
        </div>

        {/* Subject bar (email only) */}
        {isEmail && preview.subject && (
          <div className="border-b px-5 py-2.5 bg-muted/30">
            <p className="text-xs text-muted-foreground">Subject</p>
            <p className="text-sm mt-0.5">{preview.subject}</p>
          </div>
        )}

        {/* Preview content */}
        <div className="flex-1 min-h-0 overflow-y-auto">
          {isEmail ? (
            <iframe
              srcDoc={preview.body}
              title="Email preview"
              className="w-full border-0"
              style={{ minHeight: 520, height: '100%' }}
              sandbox="allow-same-origin"
            />
          ) : (
            <div className="flex justify-center p-6">
              <div className="w-full max-w-xs">
                {/* Phone-style message bubble */}
                <div className="rounded-2xl rounded-tl-sm bg-muted px-4 py-3 shadow-sm">
                  <p className="text-sm whitespace-pre-wrap leading-relaxed">{preview.body}</p>
                </div>
                <p className="text-[10px] text-muted-foreground mt-1.5 ml-1">
                  {channelLabel} · just now
                </p>
              </div>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}

export function Templates() {
  const [templates, setTemplates] = useState<TemplateData[]>([]);
  const [loading, setLoading] = useState(true);
  const [editingTemplate, setEditingTemplate] = useState<TemplateData | null>(null);

  const loadTemplates = useCallback(async () => {
    try {
      const data = await api.get<TemplateData[]>('auth/admin/templates');
      setTemplates(data);
    } catch {
      toast.error('Failed to load templates');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void loadTemplates(); }, [loadTemplates]);

  return (
    <>
      <div className="space-y-4">
        <PageHeader icon={FileText} title="Message Templates" metadata={!loading ? pluralize(templates.length, 'template') : undefined} />
        <DataTable loading={loading} isEmpty={templates.length === 0} empty={<p className="py-8 text-center text-sm text-muted-foreground">No templates found.</p>}>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Template</TableHead>
                <TableHead>Channels</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-[50px]" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {templates.map((template) => {
                const activeChannels = template.visible_channels;
                const customizedChannels = activeChannels.filter((ch) => template.channels[ch]?.override != null);

                return (
                  <TableRow key={template.id} className="even:bg-muted/30">
                    <TableCell>
                      <span className="text-sm font-medium">{template.label}</span>
                      <p className="text-xs text-muted-foreground mt-0.5">{template.description}</p>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1.5">
                        {activeChannels.map((ch) => (
                          <span key={ch} className="inline-flex text-muted-foreground">
                            {CHANNEL_ICONS[ch]}
                          </span>
                        ))}
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1.5">
                        {template.toggleable && !template.enabled && (
                          <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-muted-foreground">
                            off
                          </Badge>
                        )}
                        {customizedChannels.length > 0 && (
                          <Badge variant="default" className="text-[10px] px-1.5 py-0">
                            customized
                          </Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <button
                        type="button"
                        onClick={() => setEditingTemplate(template)}
                        className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                        aria-label={`Configure ${template.label}`}
                      >
                        <Settings className="h-4 w-4" />
                      </button>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </DataTable>
      </div>

      {editingTemplate && (
        <TemplateEditor
          template={editingTemplate}
          onClose={() => setEditingTemplate(null)}
          onSaved={loadTemplates}
        />
      )}
    </>
  );
}

// --- Provider Template Picker (generalized for any template-capable gateway) ---

function ProviderTemplatePicker({
  template,
  channel,
  channelInfo,
  onMappingSaved,
}: {
  template: TemplateData;
  channel: string;
  channelInfo: ChannelTemplateInfo;
  onMappingSaved: () => void;
}) {
  const { gateway_id: gatewayId, mapping: existingMapping, capabilities } = channelInfo;
  const isFetchable = capabilities?.fetchable ?? false;

  const [providerTemplates, setProviderTemplates] = useState<ProviderTemplateData[]>([]);
  const [loadingTemplates, setLoadingTemplates] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [selectedTemplateId, setSelectedTemplateId] = useState(existingMapping?.provider_template_id ?? '');
  const [variableMap, setVariableMap] = useState<Record<string, string>>(existingMapping?.variable_map ?? {});
  const [saving, setSaving] = useState(false);
  const [showAddForm, setShowAddForm] = useState(false);

  const selectedTemplate = useMemo(
    () => providerTemplates.find((t) => t.id === selectedTemplateId),
    [providerTemplates, selectedTemplateId],
  );

  const templateVariables = useMemo(() => {
    return Object.entries(template.variables).map(([key, v]) => ({
      key,
      label: v.label,
      example: v.example,
    }));
  }, [template.variables]);

  const providerVariables = useMemo(
    () => selectedTemplate?.variables ?? [],
    [selectedTemplate],
  );

  const previewBody = useMemo(() => {
    if (!selectedTemplate) return '';
    let body = selectedTemplate.body_text;
    for (const [varName, providerKey] of Object.entries(variableMap)) {
      const varInfo = template.variables[varName];
      if (varInfo) {
        // Replace both positional {{1}} and named {{key}} placeholders
        body = body.replace(new RegExp(`\\{\\{${providerKey}\\}\\}`, 'g'), varInfo.example);
      }
    }
    return body;
  }, [selectedTemplate, variableMap, template.variables]);

  const loadProviderTemplates = useCallback(async (refresh = false) => {
    setLoadingTemplates(true);
    setLoadError(null);
    try {
      const endpoint = refresh
        ? `gateways/${gatewayId}/templates/refresh`
        : `gateways/${gatewayId}/templates`;
      const data = refresh
        ? await api.post<ProviderTemplateData[]>(endpoint, {})
        : await api.get<ProviderTemplateData[]>(endpoint);
      setProviderTemplates(data);
    } catch {
      setLoadError('Failed to load templates from provider');
    } finally {
      setLoadingTemplates(false);
    }
  }, [gatewayId]);

  useEffect(() => {
    void loadProviderTemplates();
  }, [loadProviderTemplates]);

  async function handleSaveMapping() {
    if (!selectedTemplateId) return;
    setSaving(true);
    try {
      const source = selectedTemplate?.source === 'manual' ? 'manual' : 'catalog';
      await api.put(`gateways/${gatewayId}/template-mappings/${template.id}`, {
        provider_template_id: selectedTemplateId,
        language: selectedTemplate?.language ?? 'en',
        variable_map: variableMap,
        source,
      });
      toast.success('Template mapping saved');
      onMappingSaved();
    } catch {
      toast.error('Failed to save template mapping');
    } finally {
      setSaving(false);
    }
  }

  async function handleRemoveMapping() {
    try {
      await api.del(`gateways/${gatewayId}/template-mappings/${template.id}`);
      setSelectedTemplateId('');
      setVariableMap({});
      toast.success('Template mapping removed');
      onMappingSaved();
    } catch {
      toast.error('Failed to remove mapping');
    }
  }

  async function handleManualTemplateCreated() {
    setShowAddForm(false);
    await loadProviderTemplates();
  }

  const channelLabel = CHANNEL_LABELS[channel] ?? channel;

  return (
    <div className="space-y-4">
      <FieldDescription>
        Select a provider template for {channelLabel} messages. If no mapping is configured, the plugin falls back to the body content above.
      </FieldDescription>

      {loadError && (
        <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          {loadError}
        </div>
      )}

      <Field>
        <div className="flex items-center justify-between">
          <FieldLabel>Provider template</FieldLabel>
          <div className="flex items-center gap-1">
            {isFetchable && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => loadProviderTemplates(true)}
                disabled={loadingTemplates}
                className="h-7 gap-1.5 text-xs"
              >
                <RefreshCw className={`h-3 w-3 ${loadingTemplates ? 'animate-spin' : ''}`} />
                Refresh
              </Button>
            )}
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowAddForm(true)}
              className="h-7 gap-1.5 text-xs"
            >
              + Add
            </Button>
          </div>
        </div>

        {loadingTemplates && providerTemplates.length === 0 ? (
          <Skeleton className="h-9 w-full" />
        ) : providerTemplates.length === 0 && !loadError ? (
          <div className="rounded-md border border-border/50 px-3 py-4 text-center text-sm text-muted-foreground">
            <p>No templates found.</p>
            <p className="mt-1 text-xs">
              {isFetchable
                ? 'Create templates in your provider console, then refresh.'
                : 'Click "+ Add" to manually enter a provider template.'}
            </p>
          </div>
        ) : (
          <Select value={selectedTemplateId} onValueChange={(id) => { setSelectedTemplateId(id); setVariableMap({}); }}>
            <SelectTrigger>
              <SelectValue placeholder="None (free-form fallback)" />
            </SelectTrigger>
            <SelectContent>
              {providerTemplates.map((pt) => (
                <SelectItem key={pt.id} value={pt.id}>
                  <span className="flex items-center gap-2">
                    {pt.name}
                    <span className="text-xs text-muted-foreground">({pt.language})</span>
                    {pt.variable_count > 0 && (
                      <Badge variant="outline" className="text-[10px] px-1 py-0">
                        {pt.variable_count} var{pt.variable_count !== 1 ? 's' : ''}
                      </Badge>
                    )}
                    {pt.source === 'manual' && (
                      <Badge variant="secondary" className="text-[10px] px-1 py-0">manual</Badge>
                    )}
                  </span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </Field>

      {selectedTemplate && providerVariables.length > 0 && (
        <Field>
          <FieldLabel>Variable mapping</FieldLabel>
          <FieldDescription>
            Map each provider template variable to a plugin variable.
          </FieldDescription>
          <div className="space-y-2 mt-2">
            {providerVariables.map((pv) => (
              <div key={pv.key} className="flex items-center gap-2">
                <span className="text-xs font-mono text-muted-foreground w-20 shrink-0 truncate" title={pv.label ?? pv.key}>
                  {pv.type === 'positional' ? `{{${pv.key}}}` : pv.label ?? pv.key}
                </span>
                <Select
                  value={Object.entries(variableMap).find(([, k]) => k === pv.key)?.[0] ?? ''}
                  onValueChange={(varName) => {
                    setVariableMap((prev) => {
                      const next = { ...prev };
                      for (const [k, v] of Object.entries(next)) {
                        if (v === pv.key) delete next[k];
                      }
                      if (varName) next[varName] = pv.key;
                      return next;
                    });
                  }}
                >
                  <SelectTrigger className="flex-1">
                    <SelectValue placeholder="Select variable" />
                  </SelectTrigger>
                  <SelectContent>
                    {templateVariables.map((v) => (
                      <SelectItem key={v.key} value={v.key}>
                        {v.label}
                        <span className="ml-1 text-xs text-muted-foreground">({v.example})</span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            ))}
          </div>
        </Field>
      )}

      {selectedTemplate && (
        <Field>
          <FieldLabel>Preview</FieldLabel>
          <div className="rounded-2xl rounded-tl-sm bg-muted px-4 py-3 shadow-sm">
            <p className="text-sm whitespace-pre-wrap leading-relaxed">{previewBody}</p>
          </div>
        </Field>
      )}

      {selectedTemplate && (
        <div className="flex gap-2">
          <Button onClick={handleSaveMapping} disabled={saving} className="flex-1">
            {saving ? 'Saving...' : 'Save Mapping'}
          </Button>
          {existingMapping && (
            <Button variant="ghost" onClick={handleRemoveMapping}>
              Remove
            </Button>
          )}
        </div>
      )}

      {showAddForm && (
        <ManualTemplateForm
          gatewayId={gatewayId}
          variableStyle={capabilities?.variable_style ?? 'positional'}
          onCreated={handleManualTemplateCreated}
          onCancel={() => setShowAddForm(false)}
        />
      )}
    </div>
  );
}

// --- Manual Template Form (inline add) ---

function ManualTemplateForm({
  gatewayId,
  variableStyle,
  onCreated,
  onCancel,
}: {
  gatewayId: string;
  variableStyle: 'positional' | 'named' | null;
  onCreated: () => void;
  onCancel: () => void;
}) {
  const [templateId, setTemplateId] = useState('');
  const [name, setName] = useState('');
  const [bodyText, setBodyText] = useState('');
  const [variables, setVariables] = useState<Array<{ key: string; type: string; label?: string }>>([]);
  const [saving, setSaving] = useState(false);

  function addVariable() {
    const type = variableStyle ?? 'positional';
    const key = type === 'positional' ? String(variables.length + 1) : '';
    setVariables((prev) => [...prev, { key, type }]);
  }

  function removeVariable(index: number) {
    setVariables((prev) => prev.filter((_, i) => i !== index));
  }

  function updateVariable(index: number, field: string, value: string) {
    setVariables((prev) => prev.map((v, i) => (i === index ? { ...v, [field]: value } : v)));
  }

  async function handleSubmit() {
    if (!templateId || !name || !bodyText) {
      toast.error('Template ID, name, and body are required');
      return;
    }
    setSaving(true);
    try {
      await api.post(`gateways/${gatewayId}/templates/manual`, {
        template_id: templateId,
        name,
        body_text: bodyText,
        variables,
      });
      toast.success('Template added');
      onCreated();
    } catch {
      toast.error('Failed to add template');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-3 rounded-md border p-3 bg-muted/30">
      <p className="text-xs font-medium text-muted-foreground">Add Manual Template</p>
      <Field>
        <FieldLabel>Template ID</FieldLabel>
        <Input value={templateId} onChange={(e) => setTemplateId(e.target.value)} placeholder="e.g. otp_verify" />
      </Field>
      <Field>
        <FieldLabel>Name</FieldLabel>
        <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. OTP Verification" />
      </Field>
      <Field>
        <FieldLabel>Body Text</FieldLabel>
        <Textarea
          value={bodyText}
          onChange={(e) => setBodyText(e.target.value)}
          placeholder={variableStyle === 'named' ? 'Your code is {{otp_code}}' : 'Your code is {{1}}'}
          rows={3}
          className="font-mono text-sm"
        />
      </Field>
      <Field>
        <div className="flex items-center justify-between">
          <FieldLabel>Variables</FieldLabel>
          <Button variant="ghost" size="sm" onClick={addVariable} className="h-7 text-xs">+ Add Variable</Button>
        </div>
        <div className="space-y-2">
          {variables.map((v, i) => (
            <div key={i} className="flex items-center gap-2">
              <Input
                value={v.key}
                onChange={(e) => updateVariable(i, 'key', e.target.value)}
                placeholder={variableStyle === 'named' ? 'Variable name' : String(i + 1)}
                className="flex-1 text-sm"
                disabled={variableStyle === 'positional'}
              />
              {variableStyle === 'named' && (
                <Input
                  value={v.label ?? ''}
                  onChange={(e) => updateVariable(i, 'label', e.target.value)}
                  placeholder="Label"
                  className="flex-1 text-sm"
                />
              )}
              <Button variant="ghost" size="sm" onClick={() => removeVariable(i)} className="h-8 w-8 p-0 text-muted-foreground hover:text-destructive">
                &times;
              </Button>
            </div>
          ))}
        </div>
      </Field>
      <div className="flex gap-2">
        <Button onClick={handleSubmit} disabled={saving} size="sm">
          {saving ? 'Adding...' : 'Add Template'}
        </Button>
        <Button variant="ghost" size="sm" onClick={onCancel}>Cancel</Button>
      </div>
    </div>
  );
}

// --- Template Editor ---

function TemplateEditor({
  template,
  onClose,
  onSaved,
}: {
  template: TemplateData;
  onClose: () => void;
  onSaved: () => void;
}) {
  const visibleChannels = template.visible_channels;
  const [activeChannel, setActiveChannel] = useState(visibleChannels[0] ?? template.supported_channels[0]);
  const [drafts, setDrafts] = useState<Record<string, ChannelContentData>>({});
  const [saving, setSaving] = useState(false);
  const [preview, setPreview] = useState<PreviewData | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [enabled, setEnabled] = useState(template.enabled);

  const bodyRef = useRef<HTMLTextAreaElement>(null);
  const subjectRef = useRef<HTMLInputElement>(null);
  const ctaRef = useRef<HTMLInputElement>(null);
  const ctaUrlRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const initial: Record<string, ChannelContentData> = {};
    for (const ch of template.supported_channels) {
      const data = template.channels[ch]?.current;
      if (data) {
        initial[ch] = { ...data };
      }
    }
    setDrafts(initial);
  }, [template]);

  const currentDraft = drafts[activeChannel];
  const channelData = template.channels[activeChannel];
  const hasOverride = channelData?.override != null;
  const isEmailChannel = activeChannel === 'email';
  const activeChannelInfo = template.channel_template_info?.[activeChannel];
  const activeChannelHasTemplateSupport = !!activeChannelInfo?.capabilities?.supports_templates;
  const fieldOptions = useMemo(() => toFieldOptions(template.variables), [template.variables]);

  function updateDraft(field: keyof ChannelContentData, value: string) {
    setDrafts((prev) => ({
      ...prev,
      [activeChannel]: { ...prev[activeChannel], [field]: value },
    }));
  }

  async function handleSave() {
    if (!currentDraft) return;
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        channel: activeChannel,
        body: currentDraft.body,
        subject: currentDraft.subject ?? null,
        cta: currentDraft.cta ?? null,
        cta_url: currentDraft.cta_url ?? null,
      };
      if (template.toggleable) {
        payload.enabled = enabled;
      }
      await api.put(`auth/admin/templates/${template.id}`, payload);
      toast.success(`${CHANNEL_LABELS[activeChannel]} template saved`);
      onSaved();
    } catch {
      toast.error('Failed to save template');
    } finally {
      setSaving(false);
    }
  }

  async function handleReset() {
    try {
      await api.post(`auth/admin/templates/${template.id}/reset`, { channel: activeChannel });
      const defaultContent = channelData?.default;
      if (defaultContent) {
        setDrafts((prev) => ({ ...prev, [activeChannel]: { ...defaultContent } }));
      }
      toast.success('Reset to default');
      onSaved();
    } catch {
      toast.error('Failed to reset template');
    }
  }

  async function handlePreview() {
    setPreviewing(true);
    try {
      const data = await api.post<PreviewData>('auth/admin/templates/preview', {
        template_id: template.id,
        channel: activeChannel,
      });
      setPreview(data);
    } catch {
      toast.error('Failed to generate preview');
    } finally {
      setPreviewing(false);
    }
  }

  return (
    <>
      <Drawer open onOpenChange={(open) => { if (!open) onClose(); }}>
        <DrawerContent className="sm:max-w-md overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle>{template.label}</DrawerTitle>
            <DrawerDescription>{template.description}</DrawerDescription>
          </DrawerHeader>

          {template.toggleable && (
            <div className="flex items-center justify-between px-4 pb-2">
              <span className="text-sm font-medium">
                {enabled ? 'Enabled' : 'Disabled'}
              </span>
              <Switch
                checked={enabled}
                onCheckedChange={setEnabled}
                aria-label={enabled ? 'Disable template' : 'Enable template'}
              />
            </div>
          )}

          <div className="flex-1 overflow-y-auto px-4 pb-4 space-y-6">
            {visibleChannels.length > 0 ? (
              <>
                <Tabs value={activeChannel} onValueChange={(v) => { setActiveChannel(v); }}>
                  <TabsList>
                    {visibleChannels.map((ch) => (
                      <TabsTrigger key={ch} value={ch} className="gap-1.5">
                        {CHANNEL_ICONS[ch]}
                        {CHANNEL_LABELS[ch]}
                      </TabsTrigger>
                    ))}
                  </TabsList>

                  {visibleChannels.map((ch) => {
                    const channelInfo = template.channel_template_info?.[ch];
                    const hasTemplateSupport = !!channelInfo?.capabilities?.supports_templates;

                    return (
                    <TabsContent key={ch} value={ch} className="mt-4 space-y-4">
                      {hasTemplateSupport && channelInfo ? (
                        <ProviderTemplatePicker
                          template={template}
                          channel={ch}
                          channelInfo={channelInfo}
                          onMappingSaved={onSaved}
                        />
                      ) : currentDraft ? (
                        <>
                          {isEmailChannel && (
                            <Field>
                              <div className="flex items-center justify-between">
                                <FieldLabel>Subject</FieldLabel>
                                <TemplateVariablePicker
                                  variables={fieldOptions}
                                  popoverClassName="w-72 p-2"
                                  onInsert={(v) => insertVariableAtCursor(subjectRef.current, v, currentDraft.subject ?? '', (val) => updateDraft('subject', val))}
                                />
                              </div>
                              <Input
                                ref={subjectRef}
                                value={currentDraft.subject ?? ''}
                                onChange={(e) => updateDraft('subject', e.target.value)}
                                placeholder="Email subject line"
                              />
                            </Field>
                          )}

                          <Field>
                            <div className="flex items-center justify-between">
                              <FieldLabel>Body</FieldLabel>
                              <TemplateVariablePicker
                                variables={fieldOptions}
                                popoverClassName="w-72 p-2"
                                onInsert={(v) => insertVariableAtCursor(bodyRef.current, v, currentDraft.body, (val) => updateDraft('body', val))}
                              />
                            </div>
                            <Textarea
                              ref={bodyRef}
                              value={currentDraft.body}
                              onChange={(e) => updateDraft('body', e.target.value)}
                              rows={8}
                              className="font-mono text-sm"
                            />
                          </Field>

                          {isEmailChannel && (channelData?.default?.cta != null || channelData?.default?.cta_url != null) && (
                            <>
                              <Separator />

                              <Field>
                                <div className="flex items-center justify-between">
                                  <FieldLabel>CTA Button Text</FieldLabel>
                                  <TemplateVariablePicker
                                    variables={fieldOptions}
                                    popoverClassName="w-72 p-2"
                                    onInsert={(v) => insertVariableAtCursor(ctaRef.current, v, currentDraft.cta ?? '', (val) => updateDraft('cta', val))}
                                  />
                                </div>
                                <Input
                                  ref={ctaRef}
                                  value={currentDraft.cta ?? ''}
                                  onChange={(e) => updateDraft('cta', e.target.value)}
                                  placeholder="e.g. Verify Email"
                                />
                                <FieldDescription>
                                  Optional centered button in the email. Leave empty to omit.
                                </FieldDescription>
                              </Field>

                              <Field>
                                <div className="flex items-center justify-between">
                                  <FieldLabel>CTA Button URL</FieldLabel>
                                  <TemplateVariablePicker
                                    variables={fieldOptions}
                                    popoverClassName="w-72 p-2"
                                    onInsert={(v) => insertVariableAtCursor(ctaUrlRef.current, v, currentDraft.cta_url ?? '', (val) => updateDraft('cta_url', val))}
                                  />
                                </div>
                                <Input
                                  ref={ctaUrlRef}
                                  value={currentDraft.cta_url ?? ''}
                                  onChange={(e) => updateDraft('cta_url', e.target.value)}
                                  placeholder="e.g. {{verify_url}}"
                                />
                              </Field>
                            </>
                          )}
                        </>
                      ) : null}
                    </TabsContent>
                    );
                  })}
                </Tabs>

                {!activeChannelHasTemplateSupport && (
                  <>
                    <Separator />

                    <div className="flex gap-2">
                      <Button onClick={handleSave} disabled={saving || !currentDraft} className="flex-1">
                        {saving ? 'Saving...' : 'Save'}
                      </Button>
                      <Button variant="outline" onClick={handlePreview} disabled={previewing}>
                        <Eye className="h-3.5 w-3.5 mr-1.5" />
                        {previewing ? 'Loading...' : 'Preview'}
                      </Button>
                      {hasOverride && (
                        <Button variant="ghost" onClick={handleReset}>
                          <RotateCcw className="h-3.5 w-3.5 mr-1.5" />
                          Reset
                        </Button>
                      )}
                    </div>
                  </>
                )}
              </>
            ) : (
              <p className="text-sm text-muted-foreground">
                No channels are currently enabled for this template. Enable the relevant channels in Channel settings to customize.
              </p>
            )}
          </div>
        </DrawerContent>
      </Drawer>

      {preview && (
        <PreviewDialog
          preview={preview}
          channel={activeChannel}
          templateLabel={template.label}
          onClose={() => setPreview(null)}
        />
      )}
    </>
  );
}
