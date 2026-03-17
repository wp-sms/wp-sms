import { useRef, useState, useEffect } from 'react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatLabel } from '@/lib/constants';
import type { JsonSchema, JsonSchemaProperty } from '@/lib/api';
import { api } from '@/lib/api';
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';
import { Skeleton } from '@/components/ui/skeleton';

interface SchemaFormProps {
  schema: JsonSchema;
  values: Record<string, unknown>;
  onChange: (values: Record<string, unknown>) => void;
  payloadSchema?: JsonSchema;
  dynamicOptionsUrl?: (fieldKey: string) => string;
  placeholders?: Record<string, string>;
}

function insertVariableAtCursor(
  el: HTMLInputElement | HTMLTextAreaElement | null,
  variable: string,
  currentValue: string,
  onChange: (value: string) => void,
) {
  if (el) {
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? start;
    onChange(currentValue.slice(0, start) + variable + currentValue.slice(end));
    requestAnimationFrame(() => {
      el.focus();
      const pos = start + variable.length;
      el.setSelectionRange(pos, pos);
    });
  } else {
    onChange(variable);
  }
}

function resolvePlaceholder(
  fieldKey: string,
  prop: JsonSchemaProperty,
  triggerPlaceholder?: string,
): string {
  if (triggerPlaceholder) return triggerPlaceholder;
  if (prop.example != null) return String(prop.example);
  const label = prop.title ?? formatLabel(fieldKey);
  return `Enter ${label.toLowerCase()}`;
}

function TextareaField({
  fieldKey,
  label,
  value,
  required,
  description,
  placeholder,
  onChange,
  payloadSchema,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
  placeholder: string;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
}) {
  const ref = useRef<HTMLTextAreaElement>(null);
  return (
    <Field>
      <div className="flex items-center justify-between">
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        {payloadSchema && (
          <TemplateVariablePicker
            payloadSchema={payloadSchema}
            onInsert={(v) => insertVariableAtCursor(ref.current, v, value, (val) => onChange(fieldKey, val))}
          />
        )}
      </div>
      <Textarea
        ref={ref}
        id={`schema-${fieldKey}`}
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(fieldKey, e.target.value)}
        rows={3}
      />
      {description && <FieldDescription>{description}</FieldDescription>}
    </Field>
  );
}

function StringField({
  fieldKey,
  label,
  value,
  required,
  description,
  placeholder,
  onChange,
  payloadSchema,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
  placeholder: string;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
}) {
  const ref = useRef<HTMLInputElement>(null);
  return (
    <Field>
      <div className="flex items-center justify-between">
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        {payloadSchema && (
          <TemplateVariablePicker
            payloadSchema={payloadSchema}
            onInsert={(v) => insertVariableAtCursor(ref.current, v, value, (val) => onChange(fieldKey, val))}
          />
        )}
      </div>
      <Input
        ref={ref}
        id={`schema-${fieldKey}`}
        type="text"
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(fieldKey, e.target.value)}
      />
      {description && <FieldDescription>{description}</FieldDescription>}
    </Field>
  );
}

interface DynamicOption {
  value: string;
  label: string;
}

function DynamicSelectField({
  fieldKey,
  label,
  value,
  required,
  description,
  onChange,
  optionsUrl,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
  onChange: (key: string, value: unknown) => void;
  optionsUrl: string;
}) {
  const [options, setOptions] = useState<DynamicOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    setLoading(true);
    setError(false);
    const controller = new AbortController();
    api.get<{ options: DynamicOption[] }>(optionsUrl, { signal: controller.signal })
      .then((res) => { setOptions(res.options); setLoading(false); })
      .catch((e) => {
        if (!(e instanceof DOMException && e.name === 'AbortError')) {
          setError(true);
          setLoading(false);
        }
      });
    return () => { controller.abort(); };
  }, [optionsUrl]);

  if (loading) {
    return (
      <Field>
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        <Skeleton className="h-10 w-full" />
      </Field>
    );
  }

  if (error) {
    return (
      <Field>
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        <p className="text-sm text-destructive">Failed to load options.</p>
      </Field>
    );
  }

  return (
    <Field>
      <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
      <Select
        value={value}
        onValueChange={(v) => onChange(fieldKey, v)}
      >
        <SelectTrigger id={`schema-${fieldKey}`}>
          <SelectValue placeholder={`Select ${label.toLowerCase()}`} />
        </SelectTrigger>
        <SelectContent>
          {options.map((opt) => (
            <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
          ))}
        </SelectContent>
      </Select>
      {description && <FieldDescription>{description}</FieldDescription>}
    </Field>
  );
}

function PropertyField({
  fieldKey,
  prop,
  value,
  required,
  onChange,
  payloadSchema,
  dynamicOptionsUrl,
  triggerPlaceholder,
}: {
  fieldKey: string;
  prop: JsonSchemaProperty;
  value: unknown;
  required: boolean;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
  dynamicOptionsUrl?: (fieldKey: string) => string;
  triggerPlaceholder?: string;
}) {
  const label = prop.title ?? formatLabel(fieldKey);

  // Dynamic select: fetch options from REST endpoint
  if (prop.dynamic && dynamicOptionsUrl) {
    return (
      <DynamicSelectField
        fieldKey={fieldKey}
        label={label}
        value={String(value ?? prop.default ?? '')}
        required={required}
        description={prop.description}
        onChange={onChange}
        optionsUrl={dynamicOptionsUrl(fieldKey)}
      />
    );
  }

  if (prop.enum && prop.enum.length > 0) {
    return (
      <Field key={fieldKey}>
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        <Select
          value={String(value ?? prop.default ?? '')}
          onValueChange={(v) => onChange(fieldKey, v)}
        >
          <SelectTrigger id={`schema-${fieldKey}`}>
            <SelectValue placeholder={`Select ${label.toLowerCase()}`} />
          </SelectTrigger>
          <SelectContent>
            {prop.enum.map((opt) => (
              <SelectItem key={opt} value={opt}>{formatLabel(opt)}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        {prop.description && <FieldDescription>{prop.description}</FieldDescription>}
      </Field>
    );
  }

  if (prop.type === 'boolean') {
    return (
      <Field key={fieldKey} orientation="horizontal">
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}</FieldLabel>
        <Switch
          id={`schema-${fieldKey}`}
          checked={Boolean(value ?? prop.default ?? false)}
          onCheckedChange={(v) => onChange(fieldKey, v)}
        />
        {prop.description && <FieldDescription>{prop.description}</FieldDescription>}
      </Field>
    );
  }

  if (prop.type === 'number' || prop.type === 'integer') {
    return (
      <Field key={fieldKey}>
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        <Input
          id={`schema-${fieldKey}`}
          type="number"
          value={value != null ? String(value) : String(prop.default ?? '')}
          onChange={(e) => onChange(fieldKey, e.target.value ? Number(e.target.value) : undefined)}
        />
        {prop.description && <FieldDescription>{prop.description}</FieldDescription>}
      </Field>
    );
  }

  if (prop.type === 'object' && prop.properties) {
    const nested = (value ?? {}) as Record<string, unknown>;
    return (
      <fieldset key={fieldKey} className="space-y-3 rounded-md border border-border/50 p-4">
        <legend className="px-1 text-sm font-medium">{label}</legend>
        {prop.description && <FieldDescription>{prop.description}</FieldDescription>}
        {Object.entries(prop.properties).map(([nestedKey, nestedProp]) => (
          <PropertyField
            key={nestedKey}
            fieldKey={nestedKey}
            prop={nestedProp}
            value={nested[nestedKey]}
            required={prop.required?.includes(nestedKey) ?? false}
            onChange={(nk, nv) => onChange(fieldKey, { ...nested, [nk]: nv })}
            payloadSchema={payloadSchema}
            dynamicOptionsUrl={dynamicOptionsUrl}
          />
        ))}
      </fieldset>
    );
  }

  const strValue = String(value ?? prop.default ?? '');
  const placeholder = resolvePlaceholder(fieldKey, prop, triggerPlaceholder);
  const isTextarea = prop.type === 'text' || (prop.type === 'string' && /body|message|content|template|text/i.test(fieldKey));

  if (isTextarea) {
    return (
      <TextareaField
        fieldKey={fieldKey}
        label={label}
        value={strValue}
        required={required}
        description={prop.description}
        placeholder={placeholder}
        onChange={onChange}
        payloadSchema={payloadSchema}
      />
    );
  }

  return (
    <StringField
      fieldKey={fieldKey}
      label={label}
      value={strValue}
      required={required}
      description={prop.description}
      placeholder={placeholder}
      onChange={onChange}
      payloadSchema={payloadSchema}
    />
  );
}

export function SchemaForm({ schema, values, onChange, payloadSchema, dynamicOptionsUrl, placeholders }: SchemaFormProps) {
  if (!schema.properties || Object.keys(schema.properties).length === 0) {
    return <p className="text-sm text-muted-foreground">No configuration needed.</p>;
  }

  const requiredFields = schema.required ?? [];

  const handleChange = (key: string, value: unknown) => {
    onChange({ ...values, [key]: value });
  };

  return (
    <div className="space-y-4">
      {Object.entries(schema.properties).map(([key, prop]) => (
        <PropertyField
          key={key}
          fieldKey={key}
          prop={prop}
          value={values[key]}
          required={requiredFields.includes(key)}
          onChange={handleChange}
          payloadSchema={payloadSchema}
          dynamicOptionsUrl={dynamicOptionsUrl}
          triggerPlaceholder={placeholders?.[key]}
        />
      ))}
    </div>
  );
}
