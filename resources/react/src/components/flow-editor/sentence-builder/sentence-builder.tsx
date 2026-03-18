import { useMemo, useState } from 'react';
import type { FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent } from '@/components/ui/card';
import { useTriggers } from '@/hooks/use-triggers';
import { CollapsibleCard } from './collapsible-card';
import { StepConnector } from './step-connector';
import { TriggerSentence } from './trigger-sentence';
import { SentenceStepList } from './sentence-step-list';
import { Radio } from 'lucide-react';

interface SentenceBuilderProps {
  triggerType: string;
  triggerConfig: Record<string, unknown>;
  steps: FlowNode[];
  onChangeTrigger: (type: string, config: Record<string, unknown>) => void;
  onChangeSteps: (steps: FlowNode[]) => void;
  onPayloadSchemaChange: (schema: JsonSchema | undefined) => void;
  payloadSchema?: JsonSchema;
  flowId?: string;
  sampleData?: Record<string, unknown>;
  onSampleDataChange?: (data: Record<string, unknown> | undefined) => void;
}

export function SentenceBuilder({
  triggerType,
  triggerConfig,
  steps,
  onChangeTrigger,
  onChangeSteps,
  onPayloadSchemaChange,
  payloadSchema,
  flowId,
  sampleData,
  onSampleDataChange,
}: SentenceBuilderProps) {
  const { triggers } = useTriggers();
  const [triggerExpanded, setTriggerExpanded] = useState(!triggerType);

  const selectedTrigger = useMemo(() => triggers.find((t) => t.id === triggerType), [triggers, triggerType]);
  const triggerSummary = selectedTrigger ? `When ${selectedTrigger.name}` : 'No trigger selected';

  return (
    <Card>
      <CardContent className="pt-6 space-y-0">
        <CollapsibleCard
          icon={Radio}
          iconBg="bg-emerald-100"
          iconFg="text-emerald-600"
          borderColor="border-l-emerald-400"
          label="Trigger"
          summary={triggerSummary}
          statusColor={triggerType ? 'bg-emerald-400' : 'bg-amber-400'}
          statusLabel={triggerType ? 'Ready' : 'Needs setup'}
          isExpanded={triggerExpanded}
          onToggle={() => setTriggerExpanded(!triggerExpanded)}
        >
          <TriggerSentence
            triggerType={triggerType}
            triggerConfig={triggerConfig}
            onChangeTrigger={onChangeTrigger}
            onPayloadSchemaChange={onPayloadSchemaChange}
            payloadSchema={payloadSchema}
            flowId={flowId}
            sampleData={sampleData}
            onSampleDataChange={onSampleDataChange}
          />
        </CollapsibleCard>

        <StepConnector />

        <SentenceStepList
          steps={steps}
          onChange={onChangeSteps}
          payloadSchema={payloadSchema}
          triggerType={triggerType}
          sampleData={sampleData}
        />
      </CardContent>
    </Card>
  );
}
