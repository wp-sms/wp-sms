import { useMemo, useCallback, useEffect } from 'react';
import type { JsonSchema } from '@/lib/api';
import { useTriggers } from '@/hooks/use-triggers';
import { SchemaForm } from '@/components/schema-form';
import { useTestTrigger, TestTriggerButton, SampleDataPreview } from '@/components/flow-editor/test-trigger';
import { IntegrationPickerToken } from '@/components/flow-editor/integration-picker';
import { Skeleton } from '@/components/ui/skeleton';

interface TriggerSentenceProps {
  triggerType: string;
  triggerConfig: Record<string, unknown>;
  onChangeTrigger: (type: string, config: Record<string, unknown>) => void;
  onPayloadSchemaChange: (schema: JsonSchema | undefined) => void;
  payloadSchema?: JsonSchema;
  flowId?: string;
  sampleData?: Record<string, unknown>;
  onSampleDataChange?: (data: Record<string, unknown> | undefined) => void;
}

export function TriggerSentence({
  triggerType,
  triggerConfig,
  onChangeTrigger,
  onPayloadSchemaChange,
  payloadSchema,
  flowId,
  sampleData,
  onSampleDataChange,
}: TriggerSentenceProps) {
  const { triggers, loading } = useTriggers();
  const { testing, handleTest } = useTestTrigger({ flowId, triggerType, payloadSchema, onSampleDataChange });

  const selected = useMemo(() => triggers.find((t) => t.id === triggerType), [triggers, triggerType]);

  // Notify parent of payload schema changes only when trigger actually changes
  useEffect(() => {
    onPayloadSchemaChange(selected?.payload_schema);
  }, [selected?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const triggerOptions = useMemo(() => {
    return triggers.map((t) => ({ value: t.id, label: t.name, description: t.description, group: t.group, icon: t.icon }));
  }, [triggers]);

  const handleTriggerChange = useCallback((type: string) => {
    const trigger = triggers.find((t) => t.id === type);
    const defaults: Record<string, unknown> = {};
    if (trigger?.filter_schema) {
      for (const [key, prop] of Object.entries(trigger.filter_schema)) {
        if (prop.default !== undefined) {
          defaults[key] = prop.default;
        }
      }
    }
    onChangeTrigger(type, defaults);
  }, [onChangeTrigger, triggers]);

  const filterSchema = useMemo(() => {
    if (!selected?.filter_schema || Object.keys(selected.filter_schema).length === 0) return null;
    return { type: 'object' as const, properties: selected.filter_schema };
  }, [selected?.id, selected?.filter_schema]); // eslint-disable-line react-hooks/exhaustive-deps

  const dynamicOptionsUrl = useCallback(
    (fieldKey: string) => `triggers/${triggerType}/filter-options/${fieldKey}`,
    [triggerType],
  );

  if (loading) {
    return <Skeleton className="h-10 w-full" />;
  }

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-1.5 flex-wrap">
        <span className="text-sm font-medium text-muted-foreground">When</span>
        <IntegrationPickerToken
          items={triggerOptions}
          value={triggerType}
          onSelect={handleTriggerChange}
          placeholder="choose a trigger"
          title="Select a trigger"
          searchPlaceholder="Search triggers..."
          storageKey="triggers"
        />
        <TestTriggerButton triggerType={triggerType} flowId={flowId} testing={testing} onTest={handleTest} />
      </div>

      {filterSchema && (
        <div className="ml-8 rounded-md border border-border/50 p-3">
          <SchemaForm
            schema={filterSchema}
            values={triggerConfig}
            onChange={(vals) => onChangeTrigger(triggerType, vals)}
            dynamicOptionsUrl={dynamicOptionsUrl}
            filterMode
          />
        </div>
      )}

      <SampleDataPreview sampleData={sampleData} />
    </div>
  );
}
