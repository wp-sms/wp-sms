import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback, useMemo } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { useSaveBar } from '@/contexts/save-bar-context';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { X, BellOff } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';

interface OptOutSettings {
  auto_reply_stop_enabled: boolean;
  auto_reply_stop_text: string;
  auto_reply_start_enabled: boolean;
  auto_reply_start_text: string;
  auto_reply_help_enabled: boolean;
  auto_reply_help_text: string;
  custom_stop_keywords: string[];
  custom_start_keywords: string[];
  default_campaign_opt_out_text: string;
}

interface SettingsResponse {
  success: boolean;
  data: {
    settings: OptOutSettings;
    defaults: {
      stop_keywords: string[];
      start_keywords: string[];
      help_keywords: string[];
    };
  };
}

export function OptOutSettings({ embedded }: { embedded?: boolean }) {
  const [saved, setSaved] = useState<OptOutSettings | null>(null);
  const [draft, setDraft] = useState<OptOutSettings | null>(null);
  const [defaults, setDefaults] = useState<SettingsResponse['defaults'] | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [newStopKeyword, setNewStopKeyword] = useState('');
  const [newStartKeyword, setNewStartKeyword] = useState('');

  useEffect(() => {
    let cancelled = false;
    api.get<SettingsResponse>('optout/settings')
      .then((res) => {
        if (cancelled) return;
        setSaved(res.data.settings);
        setDraft(res.data.settings);
        setDefaults(res.data.defaults);
      })
      .catch(() => {
        if (!cancelled) toast.error(__('Failed to load opt-out settings', 'wp-sms'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const isDirty = useMemo(() => {
    return JSON.stringify(saved) !== JSON.stringify(draft);
  }, [saved, draft]);

  const update = useCallback(<K extends keyof OptOutSettings>(key: K, value: OptOutSettings[K]) => {
    setDraft((prev) => prev ? { ...prev, [key]: value } : prev);
  }, []);

  const save = useCallback(async () => {
    if (!draft) return;
    setSaving(true);
    try {
      const res = await api.put<SettingsResponse>('optout/settings', draft);
      setSaved(res.data.settings);
      setDraft(res.data.settings);
      toast.success(__('Settings saved', 'wp-sms'));
    } catch {
      toast.error(__('Failed to save settings', 'wp-sms'));
    } finally {
      setSaving(false);
    }
  }, [draft]);

  useSaveBar({ isDirty, saveStatus: saving ? 'saving' : 'idle', onSave: save });

  const addKeyword = useCallback((type: 'stop' | 'start', value: string) => {
    const keyword = value.trim().toLowerCase();
    if (!keyword) return;
    const key = type === 'stop' ? 'custom_stop_keywords' : 'custom_start_keywords';
    setDraft((prev) => {
      if (!prev) return prev;
      const current = prev[key];
      if (current.includes(keyword)) return prev;
      return { ...prev, [key]: [...current, keyword] };
    });
    if (type === 'stop') setNewStopKeyword('');
    else setNewStartKeyword('');
  }, []);

  const removeKeyword = useCallback((type: 'stop' | 'start', keyword: string) => {
    const key = type === 'stop' ? 'custom_stop_keywords' : 'custom_start_keywords';
    setDraft((prev) => {
      if (!prev) return prev;
      return { ...prev, [key]: prev[key].filter((k) => k !== keyword) };
    });
  }, []);

  if (loading || !draft || !defaults) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {!embedded && <PageHeader icon={BellOff} title={__('Opt-Out Settings', 'wp-sms')} />}
      {/* Auto-Reply Settings */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Auto-Reply Messages', 'wp-sms')}</CardTitle>
          <CardDescription>
            Configure automatic replies sent when recipients text STOP, START, or HELP keywords.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <AutoReplySection
            label="Opt-Out Reply (STOP)"
            description={__('Sent when a recipient texts STOP to unsubscribe', 'wp-sms')}
            enabled={draft.auto_reply_stop_enabled}
            text={draft.auto_reply_stop_text}
            onToggle={(v) => update('auto_reply_stop_enabled', v)}
            onTextChange={(v) => update('auto_reply_stop_text', v)}
          />
          <AutoReplySection
            label="Opt-In Reply (START)"
            description={__('Sent when a recipient texts START to re-subscribe', 'wp-sms')}
            enabled={draft.auto_reply_start_enabled}
            text={draft.auto_reply_start_text}
            onToggle={(v) => update('auto_reply_start_enabled', v)}
            onTextChange={(v) => update('auto_reply_start_text', v)}
          />
          <AutoReplySection
            label="Help Reply (HELP)"
            description={__('Sent when a recipient texts HELP for information', 'wp-sms')}
            enabled={draft.auto_reply_help_enabled}
            text={draft.auto_reply_help_text}
            onToggle={(v) => update('auto_reply_help_enabled', v)}
            onTextChange={(v) => update('auto_reply_help_text', v)}
          />
        </CardContent>
      </Card>

      {/* Keywords */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Keywords', 'wp-sms')}</CardTitle>
          <CardDescription>
            {__('Default keywords are built-in and cannot be removed. Add custom keywords for your specific needs.', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <KeywordSection
            label="STOP Keywords"
            description={__('Messages matching these keywords will unsubscribe the sender', 'wp-sms')}
            defaultKeywords={defaults.stop_keywords}
            customKeywords={draft.custom_stop_keywords}
            newKeyword={newStopKeyword}
            onNewKeywordChange={setNewStopKeyword}
            onAdd={(v) => addKeyword('stop', v)}
            onRemove={(v) => removeKeyword('stop', v)}
          />
          <KeywordSection
            label="START Keywords"
            description={__('Messages matching these keywords will re-subscribe the sender', 'wp-sms')}
            defaultKeywords={defaults.start_keywords}
            customKeywords={draft.custom_start_keywords}
            newKeyword={newStartKeyword}
            onNewKeywordChange={setNewStartKeyword}
            onAdd={(v) => addKeyword('start', v)}
            onRemove={(v) => removeKeyword('start', v)}
          />
          <Field>
            <FieldLabel>HELP Keywords</FieldLabel>
            <div className="flex flex-wrap gap-1.5">
              {defaults.help_keywords.map((kw) => (
                <Badge key={kw} variant="secondary">{kw}</Badge>
              ))}
            </div>
            <FieldDescription>Help keywords are built-in only.</FieldDescription>
          </Field>
        </CardContent>
      </Card>

      {/* Default Campaign Opt-Out Text */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Campaign Defaults', 'wp-sms')}</CardTitle>
          <CardDescription>
            {__('Default opt-out text prepopulated when creating new SMS campaigns.', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Field>
            <FieldLabel>Default opt-out text</FieldLabel>
            <Input
              value={draft.default_campaign_opt_out_text}
              onChange={(e) => update('default_campaign_opt_out_text', e.target.value)}
              placeholder={__('Reply STOP to unsubscribe', 'wp-sms')}
            />
            <FieldDescription>This text is appended to campaign messages when opt-out instructions are enabled.</FieldDescription>
          </Field>
        </CardContent>
      </Card>

    </div>
  );
}

function AutoReplySection({ label, description, enabled, text, onToggle, onTextChange }: {
  label: string;
  description: string;
  enabled: boolean;
  text: string;
  onToggle: (v: boolean) => void;
  onTextChange: (v: string) => void;
}) {
  return (
    <div className="space-y-3">
      <Field orientation="horizontal">
        <div className="flex-1">
          <FieldLabel>{label}</FieldLabel>
          <FieldDescription>{description}</FieldDescription>
        </div>
        <Switch checked={enabled} onCheckedChange={onToggle} />
      </Field>
      {enabled && (
        <Textarea
          value={text}
          onChange={(e) => onTextChange(e.target.value)}
          rows={2}
          className="ml-0"
        />
      )}
    </div>
  );
}

function KeywordSection({ label, description, defaultKeywords, customKeywords, newKeyword, onNewKeywordChange, onAdd, onRemove }: {
  label: string;
  description: string;
  defaultKeywords: string[];
  customKeywords: string[];
  newKeyword: string;
  onNewKeywordChange: (v: string) => void;
  onAdd: (v: string) => void;
  onRemove: (v: string) => void;
}) {
  return (
    <Field>
      <FieldLabel>{label}</FieldLabel>
      <FieldDescription>{description}</FieldDescription>
      <div className="flex flex-wrap gap-1.5">
        {defaultKeywords.map((kw) => (
          <Badge key={kw} variant="secondary">{kw}</Badge>
        ))}
        {customKeywords.map((kw) => (
          <Badge key={kw} variant="outline" className="gap-1">
            {kw}
            <button
              type="button"
              onClick={() => onRemove(kw)}
              className="ml-0.5 rounded-md hover:bg-muted p-0.5"
            >
              <X className="h-3 w-3" />
            </button>
          </Badge>
        ))}
      </div>
      <div className="flex gap-2 max-w-sm">
        <Input
          value={newKeyword}
          onChange={(e) => onNewKeywordChange(e.target.value)}
          placeholder={__('Add keyword...', 'wp-sms')}
          className="flex-1"
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              onAdd(newKeyword);
            }
          }}
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => onAdd(newKeyword)}
          disabled={!newKeyword.trim()}
        >
          {__('Add', 'wp-sms')}
        </Button>
      </div>
    </Field>
  );
}
