import { useRef, useState, useEffect, useMemo } from 'react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field, FieldLabel, FieldDescription, FieldHint, SwitchField } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { formatLabel } from '@/lib/constants';
import type { JsonSchema, JsonSchemaProperty } from '@/lib/api';
import { api } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';
import { Skeleton } from '@/components/ui/skeleton';
import { Plus, X } from 'lucide-react';
import { resolveTemplatePreview, extractExampleData } from '@/lib/template-preview';
import { insertVariableAtCursor } from '@/lib/text-utils';

interface SchemaFormProps {
  schema: JsonSchema;
  values: Record<string, unknown>;
  onChange: (values: Record<string, unknown>) => void;
  payloadSchema?: JsonSchema;
  dynamicOptionsUrl?: (fieldKey: string, depValues?: Record<string, unknown>) => string;
  placeholders?: Record<string, string>;
  sampleData?: Record<string, unknown>;
  filterMode?: boolean;
}

function TemplatePreview({ value, payloadSchema, sampleData }: { value: string; payloadSchema?: JsonSchema; sampleData?: Record<string, unknown> }) {
  // Memoize schema-derived examples separately so they don't recompute on every keystroke
  const schemaExamples = useMemo(() => {
    if (sampleData || !payloadSchema) return undefined;
    return extractExampleData(payloadSchema);
  }, [payloadSchema, sampleData]);

  const preview = useMemo(() => {
    if (!payloadSchema || !value) return null;
    return resolveTemplatePreview(value, payloadSchema, sampleData ?? schemaExamples);
  }, [value, payloadSchema, sampleData, schemaExamples]);

  if (!preview) return null;

  return (
    <p className="mt-1 text-xs text-muted-foreground/70 truncate">
      Preview: {preview}
    </p>
  );
}

export function isFieldVisible(prop: JsonSchemaProperty, values: Record<string, unknown>): boolean {
  if (!prop.displayOptions) return true;
  const { show, hide } = prop.displayOptions;
  if (show) {
    return Object.entries(show).every(([dep, allowed]) =>
      (allowed as unknown[]).includes(values[dep])
    );
  }
  if (hide) {
    return !Object.entries(hide).some(([dep, vals]) =>
      (vals as unknown[]).includes(values[dep])
    );
  }
  return true;
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
  hint,
  placeholder,
  onChange,
  payloadSchema,
  sampleData,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
  hint?: string;
  placeholder: string;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
  sampleData?: Record<string, unknown>;
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
      <TemplatePreview value={value} payloadSchema={payloadSchema} sampleData={sampleData} />
      {hint && <FieldHint>{hint}</FieldHint>}
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
  hint,
  placeholder,
  onChange,
  payloadSchema,
  sampleData,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
  hint?: string;
  placeholder: string;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
  sampleData?: Record<string, unknown>;
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
      <TemplatePreview value={value} payloadSchema={payloadSchema} sampleData={sampleData} />
      {hint && <FieldHint>{hint}</FieldHint>}
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
  hint,
  onChange,
  optionsUrl,
  filterMode,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
  hint?: string;
  onChange: (key: string, value: unknown) => void;
  optionsUrl: string;
  filterMode?: boolean;
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
        if (!isAbortError(e)) {
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

  const showAny = filterMode && !required;

  return (
    <Field>
      <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
      <div className="flex items-center gap-1.5">
        <Select
          value={value || undefined}
          onValueChange={(v) => onChange(fieldKey, v)}
        >
          <SelectTrigger id={`schema-${fieldKey}`} className="flex-1">
            <SelectValue placeholder={showAny ? 'Any' : `Select ${label.toLowerCase()}`} />
          </SelectTrigger>
          <SelectContent>
            {options.map((opt) => (
              <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        {showAny && value && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-9 w-9 shrink-0 p-0 text-muted-foreground hover:text-foreground"
            onClick={() => onChange(fieldKey, '')}
          >
            <X className="h-4 w-4" />
          </Button>
        )}
      </div>
      {hint && <FieldHint>{hint}</FieldHint>}
      {description && <FieldDescription>{description}</FieldDescription>}
    </Field>
  );
}

function toKvPairs(obj: Record<string, string>): [string, string][] {
  const entries = Object.entries(obj);
  return entries.length > 0 ? entries : [['', '']];
}

function fromKvPairs(pairs: [string, string][]): Record<string, string> {
  const obj: Record<string, string> = {};
  for (const [k, v] of pairs) {
    if (k) obj[k] = v;
  }
  return obj;
}

function KeyValueField({
  fieldKey,
  label,
  value,
  required,
  description,
  hint,
  onChange,
  payloadSchema,
}: {
  fieldKey: string;
  label: string;
  value: Record<string, string>;
  required: boolean;
  description?: string;
  hint?: string;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
}) {
  // Work with an array of pairs so multiple empty-key rows can coexist
  const [pairs, setPairs] = useState<[string, string][]>(() => toKvPairs(value));
  const valueRefs = useRef<(HTMLInputElement | null)[]>([]);

  // Track self-initiated commits so useEffect skips resets caused by our own onChange
  const commitCount = useRef(0);
  const lastSyncedCount = useRef(0);

  useEffect(() => {
    if (commitCount.current !== lastSyncedCount.current) {
      lastSyncedCount.current = commitCount.current;
      return; // Our own commit caused this re-render — keep local state
    }
    setPairs(toKvPairs(value)); // External change — sync
  }, [value]);

  const commit = (next: [string, string][]) => {
    setPairs(next);
    commitCount.current += 1;
    onChange(fieldKey, fromKvPairs(next));
  };

  const handleChange = (index: number, pos: 0 | 1, newVal: string) => {
    const next = pairs.map((pair, i) => {
      if (i !== index) return pair;
      const updated: [string, string] = [...pair];
      updated[pos] = newVal;
      return updated;
    });
    commit(next);
  };

  const handleRemove = (index: number) => {
    commit(pairs.filter((_, i) => i !== index));
  };

  const handleAdd = () => {
    commit([...pairs, ['', '']]);
  };

  return (
    <Field>
      <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
      <div className="space-y-2">
        {pairs.map(([k, v], index) => (
          <div key={index} className="flex items-center gap-2">
            <Input
              value={k}
              placeholder="Key"
              onChange={(e) => handleChange(index, 0, e.target.value)}
              className="flex-1"
            />
            <Input
              ref={(el) => { valueRefs.current[index] = el; }}
              value={v}
              placeholder="Value"
              onChange={(e) => handleChange(index, 1, e.target.value)}
              className="flex-1"
            />
            {payloadSchema && (
              <TemplateVariablePicker
                payloadSchema={payloadSchema}
                onInsert={(variable) => insertVariableAtCursor(
                  valueRefs.current[index],
                  variable,
                  v,
                  (val) => handleChange(index, 1, val),
                )}
              />
            )}
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 w-9 p-0 text-muted-foreground hover:text-destructive"
              onClick={() => handleRemove(index)}
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleAdd}
          className="w-full"
        >
          <Plus className="mr-1 h-3.5 w-3.5" />
          Add
        </Button>
      </div>
      {hint && <FieldHint>{hint}</FieldHint>}
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
  allValues,
  sampleData,
  filterMode,
}: {
  fieldKey: string;
  prop: JsonSchemaProperty;
  value: unknown;
  required: boolean;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
  dynamicOptionsUrl?: (fieldKey: string, depValues?: Record<string, unknown>) => string;
  triggerPlaceholder?: string;
  allValues?: Record<string, unknown>;
  sampleData?: Record<string, unknown>;
  filterMode?: boolean;
}) {
  const label = prop.title ?? formatLabel(fieldKey);

  // Dynamic select: fetch options from REST endpoint
  if (prop.dynamic && dynamicOptionsUrl) {
    // Gather dependency values for cascading selects
    const depValues: Record<string, unknown> = {};
    if (prop.dependsOn && allValues) {
      for (const dep of prop.dependsOn) {
        if (allValues[dep] != null) depValues[dep] = allValues[dep];
      }
    }
    return (
      <DynamicSelectField
        fieldKey={fieldKey}
        label={label}
        value={String(value ?? prop.default ?? '')}
        required={required}
        description={prop.description}
        hint={prop.hint}
        onChange={onChange}
        optionsUrl={dynamicOptionsUrl(fieldKey, Object.keys(depValues).length > 0 ? depValues : undefined)}
        filterMode={filterMode}
      />
    );
  }

  if (prop.enum && prop.enum.length > 0) {
    const enumValue = String(value ?? prop.default ?? '');
    const showAny = filterMode && !required;
    return (
      <Field key={fieldKey}>
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        <div className="flex items-center gap-1.5">
          <Select
            value={enumValue || undefined}
            onValueChange={(v) => onChange(fieldKey, v)}
          >
            <SelectTrigger id={`schema-${fieldKey}`} className="flex-1">
              <SelectValue placeholder={showAny ? 'Any' : `Select ${label.toLowerCase()}`} />
            </SelectTrigger>
            <SelectContent>
              {prop.enum.map((opt) => (
                <SelectItem key={opt} value={opt}>{prop.enumLabels?.[opt] ?? formatLabel(opt)}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          {showAny && enumValue && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 w-9 shrink-0 p-0 text-muted-foreground hover:text-foreground"
              onClick={() => onChange(fieldKey, '')}
            >
              <X className="h-4 w-4" />
            </Button>
          )}
        </div>
        {prop.hint && <FieldHint>{prop.hint}</FieldHint>}
        {prop.description && <FieldDescription>{prop.description}</FieldDescription>}
      </Field>
    );
  }

  if (prop.type === 'boolean') {
    return (
      <SwitchField
        key={fieldKey}
        id={`schema-${fieldKey}`}
        label={label}
        hint={prop.hint}
        description={prop.description}
        checked={Boolean(value ?? prop.default ?? false)}
        onCheckedChange={(v) => onChange(fieldKey, v)}
      />
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
        {prop.hint && <FieldHint>{prop.hint}</FieldHint>}
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

  if (prop.type === 'object' && !prop.properties) {
    return (
      <KeyValueField
        fieldKey={fieldKey}
        label={label}
        value={(value ?? {}) as Record<string, string>}
        required={required}
        description={prop.description}
        hint={prop.hint}
        onChange={onChange}
        payloadSchema={prop.template ? payloadSchema : undefined}
      />
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
        hint={prop.hint}
        placeholder={placeholder}
        onChange={onChange}
        payloadSchema={payloadSchema}
        sampleData={sampleData}
      />
    );
  }

  if (prop.format === 'date' || prop.format === 'time') {
    return (
      <Field key={fieldKey}>
        <FieldLabel htmlFor={`schema-${fieldKey}`}>{label}{required && ' *'}</FieldLabel>
        <Input
          id={`schema-${fieldKey}`}
          type={prop.format}
          value={strValue}
          onChange={(e) => onChange(fieldKey, e.target.value)}
        />
        {prop.hint && <FieldHint>{prop.hint}</FieldHint>}
        {prop.description && <FieldDescription>{prop.description}</FieldDescription>}
      </Field>
    );
  }

  return (
    <StringField
      fieldKey={fieldKey}
      label={label}
      value={strValue}
      required={required}
      description={prop.description}
      hint={prop.hint}
      placeholder={placeholder}
      onChange={onChange}
      payloadSchema={payloadSchema}
      sampleData={sampleData}
    />
  );
}

export function SchemaForm({ schema, values, onChange, payloadSchema, dynamicOptionsUrl, placeholders, sampleData, filterMode }: SchemaFormProps) {
  if (!schema.properties || Object.keys(schema.properties).length === 0) {
    return <p className="text-sm text-muted-foreground">No configuration needed.</p>;
  }

  const requiredFields = schema.required ?? [];

  const handleChange = (key: string, value: unknown) => {
    const next: Record<string, unknown> = { ...values, [key]: value };
    // When a parent field changes, clear any field that dependsOn it
    if (schema.properties) {
      for (const [depKey, depProp] of Object.entries(schema.properties)) {
        if (depProp.dependsOn?.includes(key)) {
          next[depKey] = undefined;
        }
      }
    }
    onChange(next);
  };

  return (
    <div className="space-y-4">
      {Object.entries(schema.properties)
        .filter(([, prop]) => isFieldVisible(prop, values))
        .map(([key, prop]) => (
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
            allValues={values}
            sampleData={sampleData}
            filterMode={filterMode}
          />
        ))}
    </div>
  );
}
