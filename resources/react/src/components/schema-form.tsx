import { useRef } from 'react';
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
import { TemplateVariablePicker } from '@/components/flow-editor/template-variable-picker';

interface SchemaFormProps {
  schema: JsonSchema;
  values: Record<string, unknown>;
  onChange: (values: Record<string, unknown>) => void;
  payloadSchema?: JsonSchema;
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

function TextareaField({
  fieldKey,
  label,
  value,
  required,
  description,
  onChange,
  payloadSchema,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
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
        placeholder={description}
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
  onChange,
  payloadSchema,
}: {
  fieldKey: string;
  label: string;
  value: string;
  required: boolean;
  description?: string;
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
        placeholder={description}
        onChange={(e) => onChange(fieldKey, e.target.value)}
      />
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
}: {
  fieldKey: string;
  prop: JsonSchemaProperty;
  value: unknown;
  required: boolean;
  onChange: (key: string, value: unknown) => void;
  payloadSchema?: JsonSchema;
}) {
  const label = prop.title ?? formatLabel(fieldKey);

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
          />
        ))}
      </fieldset>
    );
  }

  const strValue = String(value ?? prop.default ?? '');
  const isTextarea = prop.type === 'text' || (prop.type === 'string' && /body|message|content|template|text/i.test(fieldKey));

  if (isTextarea) {
    return (
      <TextareaField
        fieldKey={fieldKey}
        label={label}
        value={strValue}
        required={required}
        description={prop.description}
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
      onChange={onChange}
      payloadSchema={payloadSchema}
    />
  );
}

export function SchemaForm({ schema, values, onChange, payloadSchema }: SchemaFormProps) {
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
        />
      ))}
    </div>
  );
}
