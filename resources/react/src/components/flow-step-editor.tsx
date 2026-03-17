import { useState, useEffect, useMemo, useCallback } from 'react';
import { api, type ActionDefinition, type ActionNode, type ListResponse, type JsonSchema } from '@/lib/api';
import { ErrorHandlingSettings } from '@/components/flow-editor/error-handling-settings';
import { Field, FieldLabel } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectGroup,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { SchemaForm } from '@/components/schema-form';
import { Skeleton } from '@/components/ui/skeleton';
import { groupBy } from '@/lib/utils';

interface FlowStepEditorProps {
  step: ActionNode;
  stepIndex: number;
  onChange: (step: ActionNode) => void;
  payloadSchema?: JsonSchema;
  triggerType?: string;
  sampleData?: Record<string, unknown>;
}

let cachedActions: ActionDefinition[] | null = null;
let actionsFetch: Promise<ActionDefinition[]> | null = null;

export function FlowStepEditor({ step, stepIndex, onChange, payloadSchema, triggerType, sampleData }: FlowStepEditorProps) {
  const [actions, setActions] = useState<ActionDefinition[]>(cachedActions ?? []);
  const [loading, setLoading] = useState(!cachedActions);

  useEffect(() => {
    if (cachedActions) return;

    const controller = new AbortController();

    if (!actionsFetch) {
      actionsFetch = api.get<ListResponse<ActionDefinition>>('actions', { signal: controller.signal })
        .then((res) => { cachedActions = res.items; return res.items; })
        .catch((e) => { actionsFetch = null; throw e; });
    }

    actionsFetch
      .then((items) => { if (!controller.signal.aborted) { setActions(items); setLoading(false); } })
      .catch((e) => { if (!(e instanceof DOMException && e.name === 'AbortError')) setLoading(false); });

    return () => { controller.abort(); };
  }, []);

  const groups = useMemo(() => groupBy(actions, (a) => a.group), [actions]);

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

  const selected = actions.find((a) => a.id === step.action);

  const placeholders = useMemo(() => {
    const action = actions.find((a) => a.id === step.action);
    if (!action?.placeholders || !triggerType) return undefined;
    return action.placeholders[triggerType];
  }, [actions, step.action, triggerType]);

  if (loading) {
    return <Skeleton className="h-20 w-full" />;
  }

  const fieldId = `step-action-${stepIndex}`;

  return (
    <div className="space-y-3">
      <Field>
        <FieldLabel htmlFor={fieldId}>Action</FieldLabel>
        <Select
          value={step.action}
          onValueChange={(v) => onChange({ ...step, action: v, config: {} })}
        >
          <SelectTrigger id={fieldId}>
            <SelectValue placeholder="Select an action" />
          </SelectTrigger>
          <SelectContent>
            {Object.entries(groups).map(([group, items]) => (
              <SelectGroup key={group}>
                <SelectLabel>{group}</SelectLabel>
                {items.map((a) => (
                  <SelectItem key={a.id} value={a.id}>{a.name}</SelectItem>
                ))}
              </SelectGroup>
            ))}
          </SelectContent>
        </Select>
      </Field>

      {selected?.config_schema?.properties && Object.keys(selected.config_schema.properties).length > 0 && (
        <SchemaForm
          schema={selected.config_schema}
          values={step.config}
          onChange={(vals) => onChange({ ...step, config: vals })}
          payloadSchema={payloadSchema}
          dynamicOptionsUrl={dynamicOptionsUrl}
          placeholders={placeholders}
          sampleData={sampleData}
        />
      )}

      {step.action && (
        <ErrorHandlingSettings
          value={step.onError}
          onChange={(onError) => onChange({ ...step, onError })}
        />
      )}
    </div>
  );
}
