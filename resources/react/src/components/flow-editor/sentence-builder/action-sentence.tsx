import { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import type { ActionNode, JsonSchema, JsonSchemaProperty } from '@/lib/api';
import { api } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';
import { useActions } from '@/hooks/use-actions';
import { getTemplate, extractTokenFields } from '@/lib/sentence-templates';
import { SentenceToken } from './sentence-token';
import { SchemaForm, isFieldVisible } from '@/components/schema-form';
import { IntegrationPickerToken } from '@/components/flow-editor/integration-picker';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { formatLabel } from '@/lib/constants';
import { Skeleton } from '@/components/ui/skeleton';

interface ActionSentenceProps {
  step: ActionNode;
  onChange: (step: ActionNode) => void;
  payloadSchema?: JsonSchema;
  triggerType?: string;
  sampleData?: Record<string, unknown>;
}

function resolveTokenMode(prop: JsonSchemaProperty | undefined, fieldKey: string, _actionId: string): 'select' | 'dynamic-select' | 'text' | 'textarea' | 'number' {
  if (!prop) return 'text';
  if (prop.dynamic) return 'dynamic-select';
  if (prop.enum && prop.enum.length > 0) return 'select';
  if (prop.type === 'number' || prop.type === 'integer') return 'number';
  if (prop.type === 'text' || (prop.type === 'string' && /body|message|content|template|text/i.test(fieldKey))) return 'textarea';
  return 'text';
}

export function ActionSentence({ step, onChange, payloadSchema, triggerType, sampleData }: ActionSentenceProps) {
  const { actions, loading } = useActions();
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [templateVarSchema, setTemplateVarSchema] = useState<Record<string, JsonSchemaProperty> | null>(null);

  const actionOptions = useMemo(() => {
    return actions.map((a) => ({ value: a.id, label: a.name, description: a.description, group: a.group, icon: a.icon }));
  }, [actions]);

  const selected = useMemo(() => actions.find((a) => a.id === step.action), [actions, step.action]);
  const template = useMemo(() => step.action ? getTemplate(step.action) : undefined, [step.action]);
  const schema = selected?.config_schema;
  const requiredFields = useMemo(() => schema?.required ?? [], [schema]);

  const placeholders = useMemo(() => {
    if (!selected?.placeholders || !triggerType) return undefined;
    return selected.placeholders[triggerType];
  }, [selected, triggerType]);

  // Auto-fill from placeholders when trigger changes and fields are empty
  const prevTriggerType = useRef(triggerType);
  useEffect(() => {
    if (triggerType && triggerType !== prevTriggerType.current && placeholders) {
      prevTriggerType.current = triggerType;
      const updates: Record<string, unknown> = {};
      let hasUpdate = false;
      for (const [key, val] of Object.entries(placeholders)) {
        if (!step.config[key] || step.config[key] === '') {
          updates[key] = val;
          hasUpdate = true;
        }
      }
      if (hasUpdate) {
        onChange({ ...step, config: { ...step.config, ...updates } });
      }
    }
  }, [triggerType, placeholders]); // eslint-disable-line react-hooks/exhaustive-deps

  const templateId = step.config.provider_template_id as string | undefined;
  const configGateway = step.config.gateway as string | undefined;
  const configChannel = step.config.channel as string | undefined;
  const configMode = step.config.message_mode as string | undefined;

  useEffect(() => {
    if (!templateId || !step.action) {
      setTemplateVarSchema(null);
      return;
    }

    const depParams = new URLSearchParams();
    if (configGateway) depParams.set('gateway', configGateway);
    if (configChannel) depParams.set('channel', configChannel);
    if (configMode) depParams.set('message_mode', configMode);
    const qs = depParams.toString();
    const url = `actions/${step.action}/config-options/provider_template_id${qs ? `?${qs}` : ''}`;

    const controller = new AbortController();
    api.get<{ options: Array<{ value: string; meta?: Record<string, unknown> }> }>(url, { signal: controller.signal })
      .then((res) => {
        const match = res.options.find((o) => o.value === templateId);
        const vs = match?.meta?.variable_schema as Record<string, JsonSchemaProperty> | undefined;
        setTemplateVarSchema(vs ?? null);
        // Pre-populate template_variables keys if currently empty
        if (vs && !step.config.template_variables) {
          const pre = Object.fromEntries(Object.keys(vs).map((k) => [k, '']));
          onChange({ ...step, config: { ...step.config, template_variables: pre } });
        }
      })
      .catch((e) => { if (!isAbortError(e)) setTemplateVarSchema(null); });

    return () => controller.abort();
  }, [templateId, configGateway, configChannel, configMode, step.action]); // eslint-disable-line react-hooks/exhaustive-deps

  const dynamicOptionsUrl = useCallback(
    (fieldKey: string, depValues?: Record<string, unknown>) => {
      let url = `actions/${step.action}/config-options/${fieldKey}`;
      if (depValues && Object.keys(depValues).length > 0) {
        const params = new URLSearchParams();
        for (const [k, v] of Object.entries(depValues)) {
          if (v != null) params.set(k, String(v));
        }
        url += `?${params.toString()}`;
      }
      return url;
    },
    [step.action],
  );

  const handleConfigChange = useCallback((key: string, value: unknown) => {
    const next: Record<string, unknown> = { ...step.config, [key]: value };
    // Clear dependent fields
    if (schema?.properties) {
      for (const [depKey, depProp] of Object.entries(schema.properties)) {
        if (depProp.dependsOn?.includes(key)) {
          next[depKey] = undefined;
        }
      }
    }
    onChange({ ...step, config: next });
  }, [step, onChange, schema]);

  // Determine which fields go inline (sentence template) vs advanced
  // Must be before loading check to keep hooks unconditional
  const advancedSchema = useMemo(() => {
    if (!schema?.properties || !template) return null;
    const allFieldKeys = Object.keys(schema.properties);
    const advancedFieldKeys = template.advancedFields;
    const inlineFieldKeys = template.lines.flatMap(extractTokenFields);
    const remainingFields = allFieldKeys.filter(
      (k) => !inlineFieldKeys.includes(k) && !advancedFieldKeys.includes(k),
    );
    const allAdvancedKeys = [...advancedFieldKeys, ...remainingFields];
    const visibleAdvancedKeys = allAdvancedKeys.filter((k) => {
      const prop = schema.properties![k];
      return prop && isFieldVisible(prop, step.config);
    });
    if (visibleAdvancedKeys.length === 0) return null;
    const props: Record<string, JsonSchemaProperty> = {};
    for (const k of visibleAdvancedKeys) {
      if (schema.properties[k]) props[k] = schema.properties[k];
    }
    if (templateVarSchema && props['template_variables']) {
      props['template_variables'] = { ...props['template_variables'], properties: templateVarSchema };
    }
    return { type: 'object' as const, properties: props, required: requiredFields.filter((r) => visibleAdvancedKeys.includes(r)) };
  }, [schema, template, requiredFields, step.config, templateVarSchema]);

  if (loading) {
    return <Skeleton className="h-8 w-full" />;
  }

  const renderToken = (fieldKey: string) => {
    const prop = schema?.properties?.[fieldKey];
    if (!prop || !isFieldVisible(prop, step.config)) return null;

    const mode = resolveTokenMode(prop, fieldKey, step.action);
    const value = step.config[fieldKey];
    const label = prop.title ?? formatLabel(fieldKey);

    if (mode === 'select' && prop.enum) {
      return (
        <SentenceToken
          key={fieldKey}
          mode="select"
          value={String(value ?? prop.default ?? '')}
          options={prop.enum.map((e) => ({ value: e, label: prop.enumLabels?.[e] ?? formatLabel(e) }))}
          onChange={(v) => handleConfigChange(fieldKey, v)}
          placeholder={label}
        />
      );
    }

    if (mode === 'dynamic-select') {
      const depValues: Record<string, unknown> = {};
      if (prop.dependsOn) {
        for (const dep of prop.dependsOn) {
          if (step.config[dep] != null) depValues[dep] = step.config[dep];
        }
      }
      return (
        <SentenceToken
          key={fieldKey}
          mode="dynamic-select"
          value={String(value ?? '')}
          optionsUrl={dynamicOptionsUrl(fieldKey, Object.keys(depValues).length > 0 ? depValues : undefined)}
          onChange={(v) => handleConfigChange(fieldKey, v)}
          placeholder={label}
        />
      );
    }

    if (mode === 'number') {
      return (
        <SentenceToken
          key={fieldKey}
          mode="number"
          value={String(value ?? prop.default ?? 0)}
          onChange={(v) => handleConfigChange(fieldKey, v)}
          placeholder={label}
        />
      );
    }

    if (mode === 'textarea') {
      return (
        <SentenceToken
          key={fieldKey}
          mode="textarea"
          value={String(value ?? '')}
          onChange={(v) => handleConfigChange(fieldKey, v)}
          placeholder={placeholders?.[fieldKey] ?? label}
          payloadSchema={payloadSchema}
        />
      );
    }

    return (
      <SentenceToken
        key={fieldKey}
        mode="text"
        value={String(value ?? '')}
        onChange={(v) => handleConfigChange(fieldKey, v)}
        placeholder={placeholders?.[fieldKey] ?? label}
        payloadSchema={payloadSchema}
      />
    );
  };

  // Render sentence lines from template
  const renderTemplateLine = (line: string, lineIndex: number) => {
    const parts = line.split(/(\{(\w+)\})/g);
    const elements: React.ReactNode[] = [];
    let i = 0;
    while (i < parts.length) {
      // parts[i] is text before match, parts[i+1] is full match {field}, parts[i+2] is field name
      if (i + 2 < parts.length && parts[i + 1]?.startsWith('{')) {
        if (parts[i]) elements.push(<span key={`t-${lineIndex}-${i}`} className="text-sm text-muted-foreground">{parts[i]}</span>);
        const fieldKey = parts[i + 2];
        elements.push(<span key={`f-${lineIndex}-${fieldKey}`}>{renderToken(fieldKey)}</span>);
        i += 3;
      } else {
        if (parts[i]) elements.push(<span key={`t-${lineIndex}-${i}`} className="text-sm text-muted-foreground">{parts[i]}</span>);
        i++;
      }
    }
    return (
      <div key={lineIndex} className="flex items-center gap-1.5 flex-wrap">
        {elements}
      </div>
    );
  };

  return (
    <div className="space-y-2">
      {/* Action selector */}
      <div className="flex items-center gap-1.5 flex-wrap">
        <IntegrationPickerToken
          items={actionOptions}
          value={step.action}
          onSelect={(v) => onChange({ ...step, action: v, config: {} })}
          placeholder="choose an action"
          title="Select an action"
          searchPlaceholder="Search actions..."
          storageKey="actions"
        />
      </div>

      {/* Sentence template lines OR fallback SchemaForm */}
      {step.action && template && schema?.properties && (
        <div className="ml-4 space-y-1.5">
          {template.lines.map((line, i) => renderTemplateLine(line, i))}
        </div>
      )}

      {step.action && !template && schema?.properties && Object.keys(schema.properties).length > 0 && (
        <div className="ml-4">
          <SchemaForm
            schema={schema}
            values={step.config}
            onChange={(vals) => onChange({ ...step, config: vals })}
            payloadSchema={payloadSchema}
            dynamicOptionsUrl={dynamicOptionsUrl}
            placeholders={placeholders}
            sampleData={sampleData}
          />
        </div>
      )}

      {/* Advanced fields (collapsible) */}
      {step.action && template && advancedSchema && (
        <div className="ml-4">
          <button
            type="button"
            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
            onClick={() => setShowAdvanced(!showAdvanced)}
          >
            {showAdvanced ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
            Settings
          </button>
          {showAdvanced && (
            <div className="mt-2 rounded-md border border-border/50 p-3">
              <SchemaForm
                schema={advancedSchema}
                values={step.config}
                onChange={(vals) => onChange({ ...step, config: { ...step.config, ...vals } })}
                payloadSchema={payloadSchema}
                dynamicOptionsUrl={dynamicOptionsUrl}
                placeholders={placeholders}
                sampleData={sampleData}
              />
            </div>
          )}
        </div>
      )}

    </div>
  );
}
