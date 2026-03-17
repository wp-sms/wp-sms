import type { FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { TriggerSelector } from '@/components/trigger-selector';

import { FlowStepList } from '@/components/flow-step-list';
import { useTestTrigger, TestTriggerButton, SampleDataPreview } from './test-trigger';

interface AdvancedModeProps {
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

export function AdvancedMode({
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
}: AdvancedModeProps) {
  const { testing, handleTest } = useTestTrigger({ flowId, triggerType, payloadSchema, onSampleDataChange });

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base">Trigger</CardTitle>
              <CardDescription>Choose what starts this flow.</CardDescription>
            </div>
            <TestTriggerButton triggerType={triggerType} flowId={flowId} testing={testing} onTest={handleTest} />
          </div>
        </CardHeader>
        <CardContent>
          <TriggerSelector
            triggerType={triggerType}
            triggerConfig={triggerConfig}
            onChangeTrigger={onChangeTrigger}
            onPayloadSchemaChange={onPayloadSchemaChange}
          />
          <SampleDataPreview sampleData={sampleData} />
        </CardContent>
      </Card>

      <div className="space-y-3">
        <div>
          <h3 className="text-base font-semibold">Steps</h3>
          <p className="text-sm text-muted-foreground">Define the actions, conditions, and delays in this flow.</p>
        </div>
        <FlowStepList
          steps={steps}
          onChange={onChangeSteps}
          payloadSchema={payloadSchema}
          triggerType={triggerType}
          showTypePicker
          sampleData={sampleData}
        />
      </div>
    </div>
  );
}
