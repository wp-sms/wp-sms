import { __ } from '@wordpress/i18n';
import { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import type {
  Campaign, CampaignAudience, CampaignAudienceSource, CampaignCompliance,
  CampaignRecurrence, CampaignQuietHours, Tag, Gateway,
  JsonSchema,
} from '@/lib/api';
import { api, getConfig } from '@/lib/api';
import { useGateways } from '@/hooks/use-gateways';
import { useAudiencePreview } from '@/hooks/use-audience-preview';
import { SmsSegmentCounter, calculateSegments } from '@/components/campaigns/sms-segment-counter';
import { SegmentBuilder } from '@/components/lists/segment-builder';
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';
import { channelLabel } from '@/components/gateway-config-form';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  ArrowLeft, ArrowRight, Check, Send, Clock, Users, MessageSquare, Settings, FileText,
  AlertTriangle, Loader2, Upload,
} from 'lucide-react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-provider';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { cn } from '@/lib/utils';

function isSmsChannel(ch: string): boolean {
  return ch === 'sms' || ch === 'whatsapp';
}

/** Schema for campaign template variables — used by TemplateVariablePicker and preview substitution. */
const CAMPAIGN_VARIABLE_SCHEMA: JsonSchema = {
  type: 'object',
  properties: {
    first_name: { type: 'string', title: 'First Name', example: 'John' },
    last_name:  { type: 'string', title: 'Last Name', example: 'Doe' },
    phone:      { type: 'string', title: 'Phone', example: '+1234567890' },
    email:      { type: 'string', title: 'Email', example: 'john@example.com' },
    contact_id: { type: 'string', title: 'Contact ID', example: '01HX...' },
  },
};

/** Variable key → example value for preview substitution. */
const VARIABLE_EXAMPLES: Record<string, string> = {
  first_name: 'John',
  last_name: 'Doe',
  phone: '+1234567890',
  email: 'john@example.com',
  contact_id: '01HX...',
};

function getWpTimezone(): string {
  return getConfig().timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
}

interface CampaignDraft {
  name: string;
  channel: string;
  gateway_id: string;
  subject: string;
  body: string;
  media_url: string;
  audience: CampaignAudience;
  compliance: CampaignCompliance;
  send_at: string;
  send_mode: 'now' | 'scheduled';
  recurrence: CampaignRecurrence | null;
  quiet_hours: CampaignQuietHours | null;
}

interface CampaignEditorProps {
  campaign?: Campaign;
  onSave: (data: Partial<Campaign>, id?: string) => Promise<Campaign>;
  onBack: () => void;
}

const STEPS = [
  { id: 'basics', label: 'Basics', icon: Settings },
  { id: 'audience', label: 'Audience', icon: Users },
  { id: 'message', label: 'Message', icon: MessageSquare },
  { id: 'schedule', label: 'Schedule', icon: Clock },
  { id: 'review', label: 'Review', icon: FileText },
];

function getDefaultDraft(campaign?: Campaign): CampaignDraft {
  return {
    name: campaign?.name ?? '',
    channel: campaign?.channel ?? '',
    gateway_id: campaign?.gateway_id ?? '',
    subject: campaign?.subject ?? '',
    body: campaign?.body ?? '',
    media_url: campaign?.media_url ?? '',
    audience: campaign?.audience ?? { sources: [], exclude_unsubscribed: true },
    compliance: campaign?.compliance ?? { append_opt_out: true, opt_out_text: 'Reply STOP to unsubscribe' },
    send_at: campaign?.send_at ?? '',
    send_mode: campaign?.send_at ? 'scheduled' : 'now',
    recurrence: campaign?.recurrence ?? null,
    quiet_hours: campaign?.quiet_hours ?? null,
  };
}

/** Substitute `{{key}}` placeholders with example values for the preview. */
function substituteVariables(text: string): string {
  return text.replace(/\{\{(\w+)\}\}/g, (match, key) => VARIABLE_EXAMPLES[key] ?? match);
}

export function CampaignEditor({ campaign, onSave, onBack }: CampaignEditorProps) {
  const [draft, setDraft] = useState<CampaignDraft>(() => getDefaultDraft(campaign));
  const [currentStep, setCurrentStep] = useState(0);
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);
  const [savedId, setSavedId] = useState<string | undefined>(campaign?.id);
  const [tags, setTags] = useState<Tag[]>([]);
  const { gateways, loading: gatewaysLoading } = useGateways();
  const { count: audienceCount, loading: audienceLoading } = useAudiencePreview(
    draft.audience.sources.length > 0 ? draft.audience : null,
    draft.channel,
  );

  // Load tags
  useEffect(() => {
    api.get<{ items: Tag[] }>('tags').then((res) => setTags(res.items)).catch(() => {});
  }, []);

  // Refs for stable callbacks — avoids recreating handlers on every keystroke
  const draftRef = useRef(draft);
  draftRef.current = draft;
  const savedDraftRef = useRef(draft);
  const savedIdRef = useRef(savedId);
  savedIdRef.current = savedId;
  const confirm = useConfirm();

  // Unsaved changes guard for back navigation + beforeunload
  const isDirtyCheck = useCallback(() => {
    const current = draftRef.current;
    const saved = savedDraftRef.current;
    return current.name !== saved.name || current.body !== saved.body
      || current.channel !== saved.channel || current.subject !== saved.subject;
  }, []);
  const { guardedBack } = useUnsavedChanges({ isDirty: isDirtyCheck, onLeave: onBack });

  const updateDraft = useCallback(<K extends keyof CampaignDraft>(key: K, value: CampaignDraft[K]) => {
    setDraft((prev) => ({ ...prev, [key]: value }));
  }, []);

  // Channel change with confirmation via AlertDialog
  const handleChannelChange = useCallback(async (ch: string, defaultGatewayId?: string) => {
    const prev = draftRef.current;
    if (prev.body && prev.channel && prev.channel !== ch) {
      const ok = await confirm({
        title: 'Switch channel?',
        description: 'Switching channels will keep your message text but the format may differ.',
        confirmLabel: 'Switch',
      });
      if (!ok) return;
    }
    setDraft((d) => ({ ...d, channel: ch, gateway_id: defaultGatewayId ?? '' }));
  }, [confirm]);

  // Build the save payload from draft — always uses WP timezone
  const buildSavePayload = useCallback((): Partial<Campaign> => {
    const d = draftRef.current;
    return {
      name: d.name,
      channel: d.channel,
      gateway_id: d.gateway_id || null,
      subject: d.subject || null,
      body: d.body,
      audience: d.audience,
      compliance: isSmsChannel(d.channel) ? d.compliance : null,
      timezone: getWpTimezone(),
      quiet_hours: d.quiet_hours,
      recurrence: d.recurrence,
    };
  }, []);

  // Auto-save draft on step change
  const savingRef = useRef(false);
  const saveDraft = useCallback(async () => {
    const d = draftRef.current;
    if (!d.name || !d.channel) return;
    if (savingRef.current) return;
    savingRef.current = true;
    setSaving(true);
    try {
      const saved = await onSave(buildSavePayload(), savedIdRef.current);
      setSavedId(saved.id);
      savedDraftRef.current = draftRef.current;
    } catch {
      // Silent fail on auto-save
    } finally {
      savingRef.current = false;
      setSaving(false);
    }
  }, [buildSavePayload, onSave]);

  const hasAudienceSources = useMemo(() => {
    return draft.audience.sources.some((s) => {
      switch (s.type) {
        case 'segment': return !!(s.conditions?.conditions?.length || s.conditions?.groups?.length);
        case 'tags': return !!(s.tag_ids?.length);
        case 'wp_roles': return !!(s.roles?.length);
        case 'manual': return !!(s.recipients?.length);
        default: return false;
      }
    });
  }, [draft.audience.sources]);

  const canProceed = (step: number): boolean => {
    switch (step) {
      case 0: return !!draft.name && !!draft.channel;
      case 1: return hasAudienceSources;
      case 2: return !!draft.body && (draft.channel !== 'email' || !!draft.subject);
      case 3: return draft.send_mode === 'now' || !!draft.send_at;
      default: return true;
    }
  };

  const canReachStep = (target: number): boolean => {
    for (let s = currentStep; s < target; s++) {
      if (!canProceed(s)) return false;
    }
    return true;
  };

  const handleStepChange = (step: number) => {
    if (step > currentStep) {
      if (!canReachStep(step)) return;
      const d = draftRef.current;
      if (d.name && d.channel) void saveDraft();
    }
    setCurrentStep(step);
  };

  const handleSendNow = async () => {
    const count = audienceCount ?? 0;
    const ok = await confirm({
      title: 'Send campaign now?',
      description: count > 0
        ? `This will immediately send messages to ${count.toLocaleString()} recipient${count === 1 ? '' : 's'}.`
        : 'This will immediately start sending the campaign.',
      confirmLabel: 'Send Now',
    });
    if (!ok) return;

    if (!savedId) {
      setSaving(true);
      try {
        const saved = await onSave(buildSavePayload());
        setSavedId(saved.id);
        savedDraftRef.current = draftRef.current;
        setSaving(false);
        setSending(true);
        await api.post(`campaigns/${saved.id}/send`, {});
        toast.success(__('Campaign is being sent!', 'wp-sms'));
        onBack();
        return;
      } catch {
        setSaving(false);
        toast.error(__('Failed to save campaign.', 'wp-sms'));
        return;
      }
    }
    setSending(true);
    try {
      await api.post(`campaigns/${savedId}/send`, {});
      toast.success(__('Campaign is being sent!', 'wp-sms'));
      onBack();
    } catch {
      toast.error(__('Failed to send campaign.', 'wp-sms'));
    } finally {
      setSending(false);
    }
  };

  const handleSchedule = async () => {
    const d = draftRef.current;
    if (!d.send_at) {
      toast.error(__('Set a schedule time first.', 'wp-sms'));
      return;
    }
    let id = savedId;
    if (!id) {
      setSaving(true);
      try {
        const saved = await onSave(buildSavePayload());
        id = saved.id;
        setSavedId(id);
        savedDraftRef.current = draftRef.current;
      } catch {
        toast.error(__('Failed to save campaign.', 'wp-sms'));
        return;
      } finally {
        setSaving(false);
      }
    }
    setSending(true);
    try {
      await api.post(`campaigns/${id}/schedule`, { send_at: d.send_at, timezone: getWpTimezone() });
      toast.success(__('Campaign scheduled!', 'wp-sms'));
      onBack();
    } catch {
      toast.error(__('Failed to schedule campaign.', 'wp-sms'));
    } finally {
      setSending(false);
    }
  };

  const handleTestSend = async (recipient: string) => {
    if (!savedId) {
      toast.error(__('Save the campaign first.', 'wp-sms'));
      return;
    }
    try {
      const res = await api.post<{ success: boolean; data: { error?: string } }>(`campaigns/${savedId}/test`, { recipient });
      if (res.success) {
        toast.success(__('Test message sent!', 'wp-sms'));
      } else {
        toast.error(res.data.error ?? 'Test send failed.');
      }
    } catch {
      toast.error(__('Failed to send test.', 'wp-sms'));
    }
  };

  // Gateway-aware features
  const selectedGateway = useMemo(() => {
    if (!draft.gateway_id) return null;
    return gateways.find((g) => g.id === draft.gateway_id) ?? null;
  }, [draft.gateway_id, gateways]);

  const channelGateways = useMemo(() => {
    if (!draft.channel) return [];
    return gateways.filter((g) => g.is_configured && g.supported_channels.includes(draft.channel));
  }, [draft.channel, gateways]);

  // Auto-select gateway when only one option
  useEffect(() => {
    if (channelGateways.length === 1 && !draft.gateway_id) {
      updateDraft('gateway_id', channelGateways[0].id);
    }
  }, [channelGateways, draft.gateway_id, updateDraft]);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" onClick={() => void guardedBack()}>
          <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
          {__('Back', 'wp-sms')}
        </Button>
        <h2 className="text-lg font-semibold">
          {campaign ? 'Edit Campaign' : 'New Campaign'}
        </h2>
        {saving && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
      </div>

      {/* Step indicators */}
      <nav className="flex items-center gap-1">
        {STEPS.map((step, i) => {
          const Icon = step.icon;
          const isActive = i === currentStep;
          const isCompleted = i < currentStep;
          const isReachable = i <= currentStep || canReachStep(i);
          return (
            <button
              key={step.id}
              disabled={!isReachable}
              className={cn(
                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors',
                !isReachable && 'opacity-50 cursor-not-allowed text-muted-foreground',
                isReachable && isActive && 'bg-primary text-primary-foreground',
                isReachable && isCompleted && 'bg-primary/10 text-primary hover:bg-primary/20',
                isReachable && !isActive && !isCompleted && 'text-muted-foreground hover:bg-accent',
              )}
              onClick={() => handleStepChange(i)}
            >
              {isCompleted ? <Check className="h-3.5 w-3.5" /> : <Icon className="h-3.5 w-3.5" />}
              <span className="hidden sm:inline">{step.label}</span>
            </button>
          );
        })}
      </nav>

      {/* Step content */}
      <Card>
        <CardContent className="pt-6">
          {currentStep === 0 && (
            <BasicsStep
              draft={draft}
              updateDraft={updateDraft}
              onChannelChange={handleChannelChange}
              channelGateways={channelGateways}
              gatewaysLoading={gatewaysLoading}
              gateways={gateways}
            />
          )}
          {currentStep === 1 && (
            <AudienceStep
              draft={draft}
              updateDraft={updateDraft}
              tags={tags}
              audienceCount={audienceCount}
              audienceLoading={audienceLoading}
            />
          )}
          {currentStep === 2 && (
            <MessageStep
              draft={draft}
              updateDraft={updateDraft}
              selectedGateway={selectedGateway}
            />
          )}
          {currentStep === 3 && (
            <ScheduleStep draft={draft} updateDraft={updateDraft} />
          )}
          {currentStep === 4 && (
            <ReviewStep
              draft={draft}
              audienceCount={audienceCount}
              selectedGateway={selectedGateway}
              onTestSend={handleTestSend}
              onSendNow={handleSendNow}
              onSchedule={handleSchedule}
              sending={sending}
            />
          )}
        </CardContent>
      </Card>

      {/* Navigation buttons */}
      <div className="flex items-center justify-between">
        <Button
          variant="outline"
          onClick={() => handleStepChange(currentStep - 1)}
          disabled={currentStep === 0}
        >
          <ArrowLeft className="me-1 h-4 w-4 rtl:scale-x-[-1]" />
          {__('Previous', 'wp-sms')}
        </Button>
        <div className="flex gap-2">
          <Button
            variant="outline"
            onClick={() => { void saveDraft(); }}
            disabled={saving || !draft.name || !draft.channel}
          >
            {__('Save Draft', 'wp-sms')}
          </Button>
          {currentStep < STEPS.length - 1 && (
            <Button
              onClick={() => handleStepChange(currentStep + 1)}
              disabled={!canProceed(currentStep)}
            >
              Next
              <ArrowRight className="ms-1 h-4 w-4 rtl:scale-x-[-1]" />
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

// --- Step 1: Basics ---

function BasicsStep({
  draft,
  updateDraft,
  onChannelChange,
  channelGateways,
  gatewaysLoading,
  gateways,
}: {
  draft: CampaignDraft;
  updateDraft: <K extends keyof CampaignDraft>(key: K, value: CampaignDraft[K]) => void;
  onChannelChange: (ch: string, defaultGatewayId?: string) => void;
  channelGateways: Gateway[];
  gatewaysLoading: boolean;
  gateways: Gateway[];
}) {
  // Build available channels with their default (first configured) gateway
  const channelCards = useMemo(() => {
    const map = new Map<string, Gateway[]>();
    for (const gw of gateways) {
      if (!gw.is_configured) continue;
      for (const ch of gw.supported_channels) {
        if (!map.has(ch)) map.set(ch, []);
        map.get(ch)!.push(gw);
      }
    }
    return Array.from(map.entries()).map(([ch, gws]) => ({
      channel: ch,
      gateways: gws,
      defaultGateway: gws[0],
    }));
  }, [gateways]);

  // Track which card has its gateway dropdown open
  const [gatewayDropdownOpen, setGatewayDropdownOpen] = useState<string | null>(null);

  return (
    <div className="space-y-6 max-w-lg">
      <div className="space-y-2">
        <Label htmlFor="campaign-name">Campaign Name <span className="text-destructive">*</span></Label>
        <Input
          id="campaign-name"
          placeholder={__('e.g., Summer Sale Announcement', 'wp-sms')}
          value={draft.name}
          onChange={(e) => updateDraft('name', e.target.value)}
        />
      </div>

      <div className="space-y-2">
        <Label>Channel <span className="text-destructive">*</span></Label>
        {gatewaysLoading ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {[1, 2, 3].map((i) => <Skeleton key={i} className="h-20 rounded-lg" />)}
          </div>
        ) : channelCards.length === 0 ? (
          <p className="text-sm text-muted-foreground">{__('No gateways configured. Configure a gateway first.', 'wp-sms')}</p>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {channelCards.map(({ channel: ch, gateways: gws, defaultGateway }) => {
              const isSelected = draft.channel === ch;
              const currentGw = isSelected && draft.gateway_id
                ? gws.find((g) => g.id === draft.gateway_id) ?? defaultGateway
                : defaultGateway;

              return (
                <button
                  key={ch}
                  type="button"
                  className={`rounded-lg border p-3 text-sm text-start transition-colors ${
                    isSelected ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/30'
                  }`}
                  onClick={() => onChannelChange(ch, defaultGateway.id)}
                >
                  <span className="block font-medium">{channelLabel(ch)}</span>
                  <span className="block text-xs text-muted-foreground mt-0.5">
                    via {currentGw.name}
                  </span>
                  {isSelected && gws.length > 1 && (
                    <span
                      className="block text-[10px] text-primary mt-1 cursor-pointer hover:underline"
                      onClick={(e) => {
                        e.stopPropagation();
                        setGatewayDropdownOpen(gatewayDropdownOpen === ch ? null : ch);
                      }}
                    >
                      Change
                    </span>
                  )}
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Inline gateway selector when "Change" is clicked */}
      {draft.channel && gatewayDropdownOpen === draft.channel && channelGateways.length > 1 && (
        <div className="space-y-2">
          <Label htmlFor="gateway-select">{__('Gateway', 'wp-sms')}</Label>
          <Select
            value={draft.gateway_id}
            onValueChange={(v) => {
              updateDraft('gateway_id', v);
              setGatewayDropdownOpen(null);
            }}
          >
            <SelectTrigger id="gateway-select">
              <SelectValue placeholder={__('Select a gateway', 'wp-sms')} />
            </SelectTrigger>
            <SelectContent>
              {channelGateways.map((gw) => (
                <SelectItem key={gw.id} value={gw.id}>{gw.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
    </div>
  );
}

// --- Step 2: Audience ---

function AudienceStep({
  draft,
  updateDraft,
  tags,
  audienceCount,
  audienceLoading,
}: {
  draft: CampaignDraft;
  updateDraft: <K extends keyof CampaignDraft>(key: K, value: CampaignDraft[K]) => void;
  tags: Tag[];
  audienceCount: number | null;
  audienceLoading: boolean;
}) {
  const [activeTab, setActiveTab] = useState<'segment' | 'tags' | 'roles' | 'manual'>('segment');
  const roles = window.wpSmsSettings?.roles ?? {};

  const getSourceByType = (type: string): CampaignAudienceSource | undefined =>
    draft.audience.sources.find((s) => s.type === type);

  const updateSource = (type: string, source: CampaignAudienceSource) => {
    const sources = draft.audience.sources.filter((s) => s.type !== type);
    sources.push(source);
    updateDraft('audience', { ...draft.audience, sources });
  };

  const removeSource = (type: string) => {
    const sources = draft.audience.sources.filter((s) => s.type !== type);
    updateDraft('audience', { ...draft.audience, sources });
  };

  const segmentSource = getSourceByType('segment');
  const tagsSource = getSourceByType('tags');
  const rolesSource = getSourceByType('wp_roles');
  const manualSource = getSourceByType('manual');

  const TAB_LABELS: Record<string, string> = { segment: 'Segment', tags: 'Tags', roles: 'User Roles', manual: 'Manual' };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">Audience <span className="text-destructive">*</span></span>
          {audienceLoading ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
          ) : audienceCount !== null ? (
            <Badge variant="secondary">{audienceCount.toLocaleString()} recipients</Badge>
          ) : null}
        </div>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={draft.audience.exclude_unsubscribed !== false}
            onCheckedChange={(checked) => updateDraft('audience', { ...draft.audience, exclude_unsubscribed: !!checked })}
          />
          Exclude opted-out contacts
        </label>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b">
        {(['segment', 'tags', 'roles', 'manual'] as const).map((tab) => (
          <button
            key={tab}
            className={`px-3 py-2 text-sm border-b-2 transition-colors ${
              activeTab === tab
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
            onClick={() => setActiveTab(tab)}
          >
            {TAB_LABELS[tab]}
          </button>
        ))}
      </div>

      {activeTab === 'segment' && (
        <div className="space-y-3">
          <p className="text-sm text-muted-foreground">{__('Build segment conditions to target specific contacts.', 'wp-sms')}</p>
          <SegmentBuilder
            conditions={segmentSource?.conditions ?? { match: 'all', conditions: [], groups: [] }}
            tags={tags}
            onChange={(conditions) =>
              updateSource('segment', { type: 'segment', conditions })
            }
            hideCount
          />
        </div>
      )}

      {activeTab === 'tags' && (
        <div className="space-y-3">
          <p className="text-sm text-muted-foreground">{__('Select tags to include all contacts with these tags.', 'wp-sms')}</p>
          <div className="flex flex-wrap gap-2">
            {tags.map((tag) => {
              const selected = (tagsSource?.tag_ids ?? []).includes(tag.id);
              return (
                <button
                  key={tag.id}
                  type="button"
                  className={`rounded-md border px-3 py-1 text-sm transition-colors ${
                    selected ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:border-primary/30'
                  }`}
                  onClick={() => {
                    const current = tagsSource?.tag_ids ?? [];
                    const updated = selected ? current.filter((id) => id !== tag.id) : [...current, tag.id];
                    if (updated.length > 0) {
                      updateSource('tags', { type: 'tags', tag_ids: updated });
                    } else {
                      removeSource('tags');
                    }
                  }}
                >
                  {tag.name}
                  {tag.contact_count !== undefined && (
                    <span className="ms-1 text-xs text-muted-foreground">({tag.contact_count})</span>
                  )}
                </button>
              );
            })}
            {tags.length === 0 && <p className="text-sm text-muted-foreground">{__('No tags found.', 'wp-sms')}</p>}
          </div>
        </div>
      )}

      {activeTab === 'roles' && (
        <div className="space-y-3">
          <p className="text-sm text-muted-foreground">{__('Select WordPress user roles to include.', 'wp-sms')}</p>
          <div className="flex flex-wrap gap-2">
            {Object.entries(roles).map(([role, label]) => {
              const selected = (rolesSource?.roles ?? []).includes(role);
              return (
                <label key={role} className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={selected}
                    onCheckedChange={(checked) => {
                      const current = rolesSource?.roles ?? [];
                      const updated = checked ? [...current, role] : current.filter((r) => r !== role);
                      if (updated.length > 0) {
                        updateSource('wp_roles', { type: 'wp_roles', roles: updated });
                      } else {
                        removeSource('wp_roles');
                      }
                    }}
                  />
                  {label as string}
                </label>
              );
            })}
          </div>
        </div>
      )}

      {activeTab === 'manual' && (
        <div className="space-y-3">
          <p className="text-sm text-muted-foreground">
            Enter recipient numbers or addresses, one per line or comma-separated.
          </p>
          <Textarea
            rows={6}
            placeholder={draft.channel === 'email' ? 'john@example.com\njane@example.com' : '+1234567890\n+0987654321'}
            value={(manualSource?.recipients ?? []).join('\n')}
            onChange={(e) => {
              const recipients = e.target.value
                .split(/[,\n]/)
                .map((r) => r.trim())
                .filter(Boolean);
              if (recipients.length > 0) {
                updateSource('manual', { type: 'manual', recipients });
              } else {
                removeSource('manual');
              }
            }}
          />
        </div>
      )}

      <p className="text-xs text-muted-foreground">
        {__('Audience will be evaluated at send time. Count shown is an estimate.', 'wp-sms')}
      </p>
    </div>
  );
}

// --- Step 3: Message ---

function MessageStep({
  draft,
  updateDraft,
  selectedGateway,
}: {
  draft: CampaignDraft;
  updateDraft: <K extends keyof CampaignDraft>(key: K, value: CampaignDraft[K]) => void;
  selectedGateway: Gateway | null;
}) {
  const isSms = isSmsChannel(draft.channel);
  const isEmail = draft.channel === 'email';
  const supportsMms = selectedGateway?.features?.mms === true;
  const compliance = draft.compliance;
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);

  const insertVariable = useCallback((variable: string) => {
    updateDraft('body', draft.body + variable);
  }, [draft.body, updateDraft]);

  const optOutText = isSms && compliance.append_opt_out ? compliance.opt_out_text ?? '' : '';

  const handleMediaUpload = useCallback(async (file: File) => {
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await api.upload<{ url: string }>('campaigns/media', formData);
      updateDraft('media_url', res.url);
      toast.success(__('Media uploaded', 'wp-sms'));
    } catch {
      toast.error(__('Failed to upload media.', 'wp-sms'));
    } finally {
      setUploading(false);
    }
  }, [updateDraft]);

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Compose */}
        <div className="space-y-4">
          {isEmail && (
            <div className="space-y-2">
              <Label htmlFor="subject">Subject <span className="text-destructive">*</span></Label>
              <Input
                id="subject"
                placeholder={__('Email subject line', 'wp-sms')}
                value={draft.subject}
                onChange={(e) => updateDraft('subject', e.target.value)}
              />
            </div>
          )}

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label htmlFor="body">Message <span className="text-destructive">*</span></Label>
              <TemplateVariablePicker
                payloadSchema={CAMPAIGN_VARIABLE_SCHEMA}
                onInsert={insertVariable}
              />
            </div>
            <Textarea
              id="body"
              rows={8}
              placeholder={__('Type your message...', 'wp-sms')}
              value={draft.body}
              onChange={(e) => updateDraft('body', e.target.value)}
            />
            {isSms && <SmsSegmentCounter text={draft.body} optOutText={optOutText} />}
          </div>

          {/* MMS field with upload */}
          {isSms && (
            <div className="space-y-2">
              <Label className={!supportsMms ? 'text-muted-foreground' : ''}>
                {__('Media Attachment (MMS)', 'wp-sms')}
              </Label>
              {supportsMms ? (
                <div className="flex gap-2">
                  <Input
                    placeholder="https://example.com/image.jpg"
                    value={draft.media_url}
                    onChange={(e) => updateDraft('media_url', e.target.value)}
                    className="flex-1"
                  />
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) void handleMediaUpload(file);
                      e.target.value = '';
                    }}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    disabled={uploading}
                    onClick={() => fileInputRef.current?.click()}
                    title={__('Upload image', 'wp-sms')}
                  >
                    {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                  </Button>
                </div>
              ) : (
                <p className="text-xs text-muted-foreground">{__('Selected gateway does not support MMS', 'wp-sms')}</p>
              )}
            </div>
          )}

          {/* Opt-out toggle — SMS only */}
          {isSms && (
            <div className="space-y-2">
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={compliance.append_opt_out ?? true}
                  onCheckedChange={(checked) =>
                    updateDraft('compliance', { ...compliance, append_opt_out: !!checked })
                  }
                />
                Append opt-out instructions
              </label>
              {compliance.append_opt_out && (
                <>
                  <Input
                    value={compliance.opt_out_text ?? 'Reply STOP to unsubscribe'}
                    onChange={(e) =>
                      updateDraft('compliance', { ...compliance, opt_out_text: e.target.value })
                    }
                    placeholder={__('Reply STOP to unsubscribe', 'wp-sms')}
                  />
                  {selectedGateway && !selectedGateway.features.incoming && (
                    <p className="text-xs text-amber-600">
                      This gateway does not support inbound SMS. Recipients won&apos;t be able to reply STOP to unsubscribe automatically.
                    </p>
                  )}
                </>
              )}
            </div>
          )}
        </div>

        {/* Preview with variable substitution */}
        <div className="space-y-2">
          <Label>{__('Preview', 'wp-sms')}</Label>
          <div className="rounded-lg border bg-muted/30 p-4">
            {isEmail && draft.subject && (
              <p className="mb-2 font-semibold text-sm">{substituteVariables(draft.subject)}</p>
            )}
            <div className="whitespace-pre-wrap text-sm">
              {draft.body
                ? substituteVariables(draft.body)
                : <span className="text-muted-foreground italic">{__('Your message will appear here...', 'wp-sms')}</span>
              }
              {isSms && optOutText && (
                <div className="mt-4 text-xs text-muted-foreground border-t pt-2">{optOutText}</div>
              )}
            </div>
            {draft.body && /\{\{\w+\}\}/.test(draft.body) && (
              <p className="mt-3 text-[10px] text-muted-foreground">{__('Preview uses sample data', 'wp-sms')}</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// --- Step 4: Schedule ---

function ScheduleStep({
  draft,
  updateDraft,
}: {
  draft: CampaignDraft;
  updateDraft: <K extends keyof CampaignDraft>(key: K, value: CampaignDraft[K]) => void;
}) {
  const wpTz = getWpTimezone();

  return (
    <div className="space-y-6 max-w-lg">
      <div className="space-y-2">
        <Label>{__('When to send', 'wp-sms')}</Label>
        <div className="flex gap-3">
          <button
            type="button"
            className={`flex-1 rounded-lg border p-4 text-start transition-colors ${
              draft.send_mode === 'now' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/30'
            }`}
            onClick={() => updateDraft('send_mode', 'now')}
          >
            <Send className="h-5 w-5 mb-1" />
            <span className="block text-sm font-medium">{__('Send immediately', 'wp-sms')}</span>
            <span className="block text-xs text-muted-foreground">{__('Campaign starts right away', 'wp-sms')}</span>
          </button>
          <button
            type="button"
            className={`flex-1 rounded-lg border p-4 text-start transition-colors ${
              draft.send_mode === 'scheduled' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/30'
            }`}
            onClick={() => updateDraft('send_mode', 'scheduled')}
          >
            <Clock className="h-5 w-5 mb-1" />
            <span className="block text-sm font-medium">{__('Schedule for later', 'wp-sms')}</span>
            <span className="block text-xs text-muted-foreground">{__('Pick a date and time', 'wp-sms')}</span>
          </button>
        </div>
      </div>

      {draft.send_mode === 'scheduled' && (
        <div className="space-y-2">
          <Label htmlFor="send-at">Date & Time <span className="text-destructive">*</span></Label>
          <Input
            id="send-at"
            type="datetime-local"
            value={draft.send_at}
            onChange={(e) => updateDraft('send_at', e.target.value)}
          />
          <p className="text-xs text-muted-foreground">
            Times are in your site's timezone ({wpTz})
          </p>
        </div>
      )}

      {/* Quiet hours */}
      <div className="space-y-2">
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={!!draft.quiet_hours}
            onCheckedChange={(checked) =>
              updateDraft(
                'quiet_hours',
                checked ? { start: '21:00', end: '08:00', timezone: wpTz } : null,
              )
            }
          />
          Enable quiet hours
        </label>
        <p className="text-xs text-muted-foreground ms-6">
          Delay messages during restricted hours (e.g., 9 PM - 8 AM) for TCPA compliance.
        </p>
        {draft.quiet_hours && (
          <div className="flex gap-3 ms-6">
            <div className="space-y-1">
              <Label className="text-xs">{__('Start', 'wp-sms')}</Label>
              <Input
                type="time"
                value={draft.quiet_hours.start}
                onChange={(e) => updateDraft('quiet_hours', { ...draft.quiet_hours!, start: e.target.value })}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{__('End', 'wp-sms')}</Label>
              <Input
                type="time"
                value={draft.quiet_hours.end}
                onChange={(e) => updateDraft('quiet_hours', { ...draft.quiet_hours!, end: e.target.value })}
              />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// --- Step 5: Review ---

function ReviewStep({
  draft,
  audienceCount,
  selectedGateway,
  onTestSend,
  onSendNow,
  onSchedule,
  sending,
}: {
  draft: CampaignDraft;
  audienceCount: number | null;
  selectedGateway: Gateway | null;
  onTestSend: (recipient: string) => Promise<void>;
  onSendNow: () => Promise<void>;
  onSchedule: () => Promise<void>;
  sending: boolean;
}) {
  const [testRecipient, setTestRecipient] = useState('');
  const [testSending, setTestSending] = useState(false);

  const isSms = isSmsChannel(draft.channel);
  const segmentInfo = isSms ? calculateSegments(
    isSms && draft.compliance.append_opt_out
      ? `${draft.body}\n\n${draft.compliance.opt_out_text ?? ''}`
      : draft.body,
  ) : null;

  // Warnings
  const warnings: { message: string; severity: 'warning' | 'info' }[] = [];

  if (isSms && !draft.compliance.append_opt_out) {
    warnings.push({
      message: 'SMS campaigns should include opt-out instructions (e.g., "Reply STOP to unsubscribe") for TCPA compliance.',
      severity: 'warning',
    });
  }

  if (segmentInfo && segmentInfo.segmentCount > 3) {
    warnings.push({
      message: `Your message will be split into ${segmentInfo.segmentCount} segments, increasing cost per recipient.`,
      severity: 'warning',
    });
  }

  if (segmentInfo && segmentInfo.encoding === 'Unicode') {
    warnings.push({
      message: 'Unicode characters detected — SMS capacity reduced from 160 to 70 chars per segment.',
      severity: 'info',
    });
  }

  if ((audienceCount ?? 0) > 1000) {
    warnings.push({
      message: `Consider sending a test message before sending to ${(audienceCount ?? 0).toLocaleString()} recipients.`,
      severity: 'info',
    });
  }

  const supportsDeliveryReceipt = selectedGateway?.features?.delivery_receipt === true;

  const handleTestSend = async () => {
    if (!testRecipient) return;
    setTestSending(true);
    try {
      await onTestSend(testRecipient);
    } finally {
      setTestSending(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Summary */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <SummaryItem label={__('Campaign', 'wp-sms')} value={draft.name} />
        <SummaryItem label={__('Channel', 'wp-sms')} value={sprintf(__('%1$s via %2$s', 'wp-sms'), channelLabel(draft.channel), selectedGateway?.name ?? __('Default', 'wp-sms'))} />
        <SummaryItem label={__('Recipients', 'wp-sms')} value={audienceCount !== null ? audienceCount.toLocaleString() : __('Calculating...', 'wp-sms')} />
        <SummaryItem
          label={__('Schedule', 'wp-sms')}
          value={draft.send_mode === 'now' ? __('Send immediately', 'wp-sms') : `${__('Scheduled', 'wp-sms')}: ${draft.send_at}`}
        />
        {segmentInfo && (
          <SummaryItem
            label={__('SMS Segments', 'wp-sms')}
            value={sprintf(__('%1$d segments (%2$s)', 'wp-sms'), segmentInfo.segmentCount, segmentInfo.encoding)}
          />
        )}
        {draft.quiet_hours && (
          <SummaryItem label={__('Quiet Hours', 'wp-sms')} value={`${draft.quiet_hours.start} - ${draft.quiet_hours.end}`} />
        )}
      </div>

      {!supportsDeliveryReceipt && (
        <p className="text-sm text-muted-foreground bg-muted/50 rounded-lg p-3">
          {__('Delivery tracking not available for this gateway — only send status will be shown.', 'wp-sms')}
        </p>
      )}

      {/* Warnings */}
      {warnings.length > 0 && (
        <div className="space-y-2">
          {warnings.map((w, i) => (
            <Alert key={i} variant={w.severity === 'warning' ? 'warning' : 'info'} role="status">
              <AlertTriangle className="h-4 w-4" />
              <AlertDescription>{w.message}</AlertDescription>
            </Alert>
          ))}
        </div>
      )}

      {/* Test send */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-sm">{__('Send Test', 'wp-sms')}</CardTitle>
          <CardDescription>{__('Send a test message to a single recipient before launching.', 'wp-sms')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-2">
            <Input
              placeholder={draft.channel === 'email' ? 'test@example.com' : '+1234567890'}
              value={testRecipient}
              onChange={(e) => setTestRecipient(e.target.value)}
            />
            <Button
              variant="outline"
              onClick={() => void handleTestSend()}
              disabled={!testRecipient || testSending}
            >
              {testSending ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Send Test'}
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Action buttons */}
      <div className="flex justify-end gap-3 pt-4 border-t">
        {draft.send_mode === 'now' ? (
          <Button onClick={() => void onSendNow()} disabled={sending} size="lg">
            {sending ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : <Send className="me-2 h-4 w-4" />}
            Send Now {audienceCount !== null && `(${audienceCount.toLocaleString()} recipients)`}
          </Button>
        ) : (
          <Button onClick={() => void onSchedule()} disabled={sending || !draft.send_at} size="lg">
            {sending ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : <Clock className="me-2 h-4 w-4" />}
            Schedule Campaign
          </Button>
        )}
      </div>
    </div>
  );
}

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border p-3">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-sm font-medium">{value}</p>
    </div>
  );
}
