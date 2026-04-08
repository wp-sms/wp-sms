import { __ } from '@wordpress/i18n';
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription, SwitchField } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Eye, EyeOff } from 'lucide-react';
import { DynamicConfigField } from '@/components/dynamic-config-field';
import type { GatewayConfigSchema, GatewayConfigField, GatewayConfig } from '@/lib/api';
import { CHANNEL_LABELS, channelLabel } from '@/lib/channel';

const SAVE_DEBOUNCE_MS = 600;

interface GatewayConfigFormProps {
  gatewayId: string;
  schema: GatewayConfigSchema;
  values: GatewayConfig;
  supportedChannels: string[];
  onChange: (config: GatewayConfig) => void;
}

export interface GatewayConfigFormHandle {
  /**
   * Synchronously flush any pending debounced save. Call before operations
   * that depend on the server having the latest typed values (e.g. test
   * connection, switching gateways, wizard navigation).
   */
  flush: () => void;
}

function SecretField({ id, value, onChange, placeholder }: {
  id: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
}) {
  const [visible, setVisible] = useState(false);
  return (
    <div className="relative" dir="ltr">
      <Input
        id={id}
        type={visible ? 'text' : 'password'}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="pe-10"
      />
      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        className="absolute end-2 top-1/2 -translate-y-1/2"
        onClick={() => setVisible(!visible)}
        aria-label={visible ? __('Hide password', 'wp-sms') : __('Show password', 'wp-sms')}
      >
        {visible ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
      </Button>
    </div>
  );
}

function ConfigField({ fieldKey, field, value, onChange }: {
  fieldKey: string;
  field: GatewayConfigField;
  value: unknown;
  onChange: (key: string, value: unknown) => void;
}) {
  const id = `gateway-${fieldKey}`;

  if (field.type === 'boolean') {
    return (
      <SwitchField
        id={id}
        label={field.label}
        description={field.description}
        checked={Boolean(value ?? field.default ?? false)}
        onCheckedChange={(v) => onChange(fieldKey, v)}
      />
    );
  }

  if (field.type === 'select' && field.options) {
    return (
      <Field>
        <FieldLabel htmlFor={id}>{field.label}{field.required && ' *'}</FieldLabel>
        <Select
          value={String(value ?? field.default ?? '')}
          onValueChange={(v) => onChange(fieldKey, v)}
        >
          <SelectTrigger id={id}>
            <SelectValue placeholder={`Select ${field.label.toLowerCase()}`} />
          </SelectTrigger>
          <SelectContent>
            {field.options.map((opt) => (
              <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        {field.description && <FieldDescription>{field.description}</FieldDescription>}
      </Field>
    );
  }

  if (field.type === 'secret') {
    return (
      <Field>
        <FieldLabel htmlFor={id}>{field.label}{field.required && ' *'}</FieldLabel>
        <SecretField
          id={id}
          value={String(value ?? '')}
          onChange={(v) => onChange(fieldKey, v)}
          placeholder={field.placeholder}
        />
        {field.description && <FieldDescription>{field.description}</FieldDescription>}
      </Field>
    );
  }

  if (field.type === 'number') {
    return (
      <Field>
        <FieldLabel htmlFor={id}>{field.label}{field.required && ' *'}</FieldLabel>
        <Input
          id={id}
          type="number"
          value={value != null ? String(value) : String(field.default ?? '')}
          onChange={(e) => onChange(fieldKey, e.target.value ? Number(e.target.value) : undefined)}
          placeholder={field.placeholder}
        />
        {field.description && <FieldDescription>{field.description}</FieldDescription>}
      </Field>
    );
  }

  // Default: string
  return (
    <Field>
      <FieldLabel htmlFor={id}>{field.label}{field.required && ' *'}</FieldLabel>
      <Input
        id={id}
        type="text"
        dir="ltr"
        value={String(value ?? field.default ?? '')}
        onChange={(e) => onChange(fieldKey, e.target.value)}
        placeholder={field.placeholder}
      />
      {field.description && <FieldDescription>{field.description}</FieldDescription>}
    </Field>
  );
}

export const GatewayConfigForm = forwardRef<GatewayConfigFormHandle, GatewayConfigFormProps>(
  function GatewayConfigForm({ gatewayId, schema, values, supportedChannels, onChange }, ref) {
    // Local buffer for the form. Typing updates this immediately so the input
    // stays responsive; the parent onChange (which hits the API + refetches)
    // is only called after a debounce. Without this, the fully-controlled form
    // would fire an API PUT per keystroke and unmount when the refetch toggled
    // the parent's loading state.
    const [localValues, setLocalValues] = useState<GatewayConfig>(values);

    // Reset local state when switching gateways. Intentionally keyed only on
    // gatewayId — not on `values` — so a refetch that returns the same gateway
    // does not stomp on in-progress edits.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => { setLocalValues(values); }, [gatewayId]);

    const debounceRef = useRef<ReturnType<typeof setTimeout>>();
    const pendingRef = useRef<GatewayConfig | null>(null);
    const onChangeRef = useRef(onChange);
    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

    const flush = useCallback(() => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
        debounceRef.current = undefined;
      }
      const pending = pendingRef.current;
      if (pending) {
        pendingRef.current = null;
        onChangeRef.current(pending);
      }
    }, []);

    const scheduleSave = useCallback((next: GatewayConfig) => {
      pendingRef.current = next;
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        debounceRef.current = undefined;
        const v = pendingRef.current;
        pendingRef.current = null;
        if (v) onChangeRef.current(v);
      }, SAVE_DEBOUNCE_MS);
    }, []);

    useImperativeHandle(ref, () => ({ flush }), [flush]);

    // Flush any pending save on unmount so edits aren't lost when the user
    // clicks Continue (which unmounts GatewayStep). The save is fire-and-
    // forget at that point — the promise chain in updateConfig runs to
    // completion regardless of mount state.
    useEffect(() => {
      return () => { flush(); };
    }, [flush]);

    const shared = localValues.shared ?? {};
    const channels = localValues.channels ?? {};

    const hasSharedFields = Object.keys(schema.shared ?? {}).length > 0;
    const channelSections = supportedChannels.filter(
      (ch) => schema.channels?.[ch] && Object.keys(schema.channels[ch]).length > 0
    );
    const hasAnyFields = hasSharedFields || channelSections.length > 0;

    function updateShared(key: string, val: unknown) {
      const next: GatewayConfig = {
        ...localValues,
        shared: { ...shared, [key]: val },
      };
      setLocalValues(next);
      scheduleSave(next);
    }

    function updateChannel(channel: string, key: string, val: unknown) {
      const next: GatewayConfig = {
        ...localValues,
        channels: {
          ...channels,
          [channel]: { ...(channels[channel] ?? {}), [key]: val },
        },
      };
      setLocalValues(next);
      scheduleSave(next);
    }

    return (
      <div className="space-y-6">
        {hasSharedFields && (
          <div className="space-y-4">
            <h4 className="text-sm font-medium">{__('Credentials', 'wp-sms')}</h4>
            {Object.entries(schema.shared).map(([key, field]) => (
              <ConfigField
                key={key}
                fieldKey={key}
                field={field}
                value={shared[key]}
                onChange={updateShared}
              />
            ))}
          </div>
        )}

        {channelSections.map((channel) => (
          <div key={channel} className="space-y-4">
            <h4 className="text-sm font-medium">{channelLabel(channel)} {__('Settings', 'wp-sms')}</h4>
            {Object.entries(schema.channels[channel]).map(([key, field]) =>
              field.dynamic ? (
                <DynamicConfigField
                  key={key}
                  fieldKey={key}
                  field={field}
                  value={(channels[channel] ?? {})[key]}
                  onChange={(k, v) => updateChannel(channel, k, v)}
                  gatewayId={gatewayId}
                  section={channel}
                  draftConfig={localValues}
                  sharedSchema={schema.shared}
                />
              ) : (
                <ConfigField
                  key={key}
                  fieldKey={key}
                  field={field}
                  value={(channels[channel] ?? {})[key]}
                  onChange={(k, v) => updateChannel(channel, k, v)}
                />
              )
            )}
          </div>
        ))}

        {!hasAnyFields && supportedChannels.length === 0 && (
          <p className="text-sm text-muted-foreground">{__('No configuration needed.', 'wp-sms')}</p>
        )}
      </div>
    );
  }
);

function ensureConfig(config: GatewayConfig | undefined): GatewayConfig {
  return {
    shared: config?.shared ?? {},
    channels: config?.channels ?? {},
    is_default: config?.is_default ?? {},
  };
}

export { CHANNEL_LABELS, channelLabel, ensureConfig };
