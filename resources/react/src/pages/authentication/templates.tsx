import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription } from '@/components/ui/drawer';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';
import { insertVariableAtCursor } from '@/lib/text-utils';
import type { FieldOption } from '@/lib/condition-utils';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Mail, MessageSquare, Send, Smartphone, Eye, RotateCcw, Settings } from 'lucide-react';

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

interface TemplateData {
  id: string;
  label: string;
  description: string;
  supported_channels: string[];
  visible_channels: string[];
  variables: Record<string, VariableInfo>;
  channels: Record<string, ChannelEditData>;
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
      <DialogContent className={`z-[10000] max-h-[85vh] flex flex-col gap-0 p-0 overflow-hidden ${isEmail ? 'max-w-3xl' : 'max-w-md'}`}>
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
              sandbox=""
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

  if (loading) {
    return <Skeleton className="h-64 w-full" />;
  }

  return (
    <>
      <div className="rounded-lg border border-border/50 divide-y divide-border/50">
        {templates.map((template) => {
          const activeChannels = template.visible_channels;
          const customizedChannels = activeChannels.filter((ch) => template.channels[ch]?.override != null);

          return (
            <div key={template.id} className="flex items-center gap-3 px-4 py-3">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">{template.label}</span>
                  {customizedChannels.length > 0 && (
                    <Badge variant="default" className="text-[10px] px-1.5 py-0">
                      customized
                    </Badge>
                  )}
                </div>
                <div className="flex items-center gap-1.5 mt-1">
                  <p className="text-xs text-muted-foreground">{template.description}</p>
                  {activeChannels.length > 0 && (
                    <span className="text-xs text-muted-foreground">·</span>
                  )}
                  {activeChannels.map((ch) => (
                    <span key={ch} className="inline-flex items-center gap-0.5 text-xs text-muted-foreground">
                      {CHANNEL_ICONS[ch]}
                    </span>
                  ))}
                </div>
              </div>
              <button
                type="button"
                onClick={() => setEditingTemplate(template)}
                className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                aria-label={`Configure ${template.label}`}
              >
                <Settings className="h-4 w-4" />
              </button>
            </div>
          );
        })}
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
      await api.put(`auth/admin/templates/${template.id}`, {
        channel: activeChannel,
        body: currentDraft.body,
        subject: currentDraft.subject ?? null,
        cta: currentDraft.cta ?? null,
        cta_url: currentDraft.cta_url ?? null,
      });
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
      <Drawer open onOpenChange={(open) => { if (!open) onClose(); }} direction="right">
        <DrawerContent className="sm:max-w-md overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle>{template.label}</DrawerTitle>
            <DrawerDescription>{template.description}</DrawerDescription>
          </DrawerHeader>

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

                  {visibleChannels.map((ch) => (
                    <TabsContent key={ch} value={ch} className="mt-4 space-y-4">
                      {currentDraft && (
                        <>
                          {isEmailChannel && (
                            <Field>
                              <div className="flex items-center justify-between">
                                <FieldLabel>Subject</FieldLabel>
                                <TemplateVariablePicker
                                  variables={fieldOptions}
                                  popoverClassName="z-[10000] w-72 p-2"
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
                                popoverClassName="z-[10000] w-72 p-2"
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

                          {isEmailChannel && (
                            <>
                              <Separator />

                              <Field>
                                <div className="flex items-center justify-between">
                                  <FieldLabel>CTA Button Text</FieldLabel>
                                  <TemplateVariablePicker
                                    variables={fieldOptions}
                                    popoverClassName="z-[10000] w-72 p-2"
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
                                    popoverClassName="z-[10000] w-72 p-2"
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
                      )}
                    </TabsContent>
                  ))}
                </Tabs>

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
