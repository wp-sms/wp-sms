import { useState, useEffect, useMemo, useCallback } from 'react';
import { api, type TriggerDefinition, type ListResponse, type JsonSchema } from '@/lib/api';
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

interface TriggerSelectorProps {
  triggerType: string;
  triggerConfig: Record<string, unknown>;
  onChangeTrigger: (type: string, config: Record<string, unknown>) => void;
  onPayloadSchemaChange?: (schema: JsonSchema | undefined) => void;
}

let cachedTriggers: TriggerDefinition[] | null = null;
let triggersFetch: Promise<TriggerDefinition[]> | null = null;

export function TriggerSelector({ triggerType, triggerConfig, onChangeTrigger, onPayloadSchemaChange }: TriggerSelectorProps) {
  const [triggers, setTriggers] = useState<TriggerDefinition[]>(cachedTriggers ?? []);
  const [loading, setLoading] = useState(!cachedTriggers);

  useEffect(() => {
    if (cachedTriggers) return;

    const controller = new AbortController();

    if (!triggersFetch) {
      triggersFetch = api.get<ListResponse<TriggerDefinition>>('triggers', { signal: controller.signal })
        .then((res) => { cachedTriggers = res.items; return res.items; })
        .catch((e) => { triggersFetch = null; throw e; });
    }

    triggersFetch
      .then((items) => { if (!controller.signal.aborted) { setTriggers(items); setLoading(false); } })
      .catch((e) => { if (!(e instanceof DOMException && e.name === 'AbortError')) setLoading(false); });

    return () => { controller.abort(); };
  }, []);

  const groups = useMemo(() => groupBy(triggers, (t) => t.group), [triggers]);

  const selected = useMemo(() => triggers.find((t) => t.id === triggerType), [triggers, triggerType]);

  // Notify parent of payload schema changes (use stable primitive dep to avoid infinite loops)
  useEffect(() => {
    onPayloadSchemaChange?.(selected?.payload_schema);
  }, [selected?.id, onPayloadSchemaChange]); // eslint-disable-line react-hooks/exhaustive-deps -- selected.id is stable proxy for payload_schema

  // Build a JsonSchema wrapper from the flat filter_schema record
  const filterSchema = useMemo(() => {
    if (!selected?.filter_schema || Object.keys(selected.filter_schema).length === 0) return null;
    return {
      type: 'object' as const,
      properties: selected.filter_schema,
    };
  }, [selected?.id, selected?.filter_schema]); // eslint-disable-line react-hooks/exhaustive-deps -- selected.id is unnecessary per ESLint but kept as defensive invalidation guard

  // Build dynamic options URL resolver for this trigger
  const dynamicOptionsUrl = useCallback(
    (fieldKey: string) => `triggers/${triggerType}/filter-options/${fieldKey}`,
    [triggerType],
  );

  if (loading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-10 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <Field>
        <FieldLabel htmlFor="trigger-type">Trigger</FieldLabel>
        <Select
          value={triggerType}
          onValueChange={(v) => onChangeTrigger(v, {})}
        >
          <SelectTrigger id="trigger-type">
            <SelectValue placeholder="Select a trigger" />
          </SelectTrigger>
          <SelectContent>
            {Object.entries(groups).map(([group, items]) => (
              <SelectGroup key={group}>
                <SelectLabel>{group}</SelectLabel>
                {items.map((t) => (
                  <SelectItem key={t.id} value={t.id}>{t.name}</SelectItem>
                ))}
              </SelectGroup>
            ))}
          </SelectContent>
        </Select>
      </Field>

      {filterSchema && (
        <div className="rounded-md border border-border/50 p-4 space-y-3">
          <p className="text-sm font-medium text-muted-foreground">Filter Configuration</p>
          <SchemaForm
            schema={filterSchema}
            values={triggerConfig}
            onChange={(vals) => onChangeTrigger(triggerType, vals)}
            dynamicOptionsUrl={dynamicOptionsUrl}
          />
        </div>
      )}
    </div>
  );
}
