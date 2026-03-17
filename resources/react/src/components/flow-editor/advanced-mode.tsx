import type { FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { TriggerSelector } from '@/components/trigger-selector';
import { FlowStepList } from '@/components/flow-step-list';

interface AdvancedModeProps {
  triggerType: string;
  triggerConfig: Record<string, unknown>;
  steps: FlowNode[];
  onChangeTrigger: (type: string, config: Record<string, unknown>) => void;
  onChangeSteps: (steps: FlowNode[]) => void;
  onPayloadSchemaChange: (schema: JsonSchema | undefined) => void;
  payloadSchema?: JsonSchema;
}

export function AdvancedMode({
  triggerType,
  triggerConfig,
  steps,
  onChangeTrigger,
  onChangeSteps,
  onPayloadSchemaChange,
  payloadSchema,
}: AdvancedModeProps) {
  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Trigger</CardTitle>
          <CardDescription>Choose what starts this flow.</CardDescription>
        </CardHeader>
        <CardContent>
          <TriggerSelector
            triggerType={triggerType}
            triggerConfig={triggerConfig}
            onChangeTrigger={onChangeTrigger}
            onPayloadSchemaChange={onPayloadSchemaChange}
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Steps</CardTitle>
          <CardDescription>Define the actions, conditions, and delays in this flow.</CardDescription>
        </CardHeader>
        <CardContent>
          <FlowStepList
            steps={steps}
            onChange={onChangeSteps}
            payloadSchema={payloadSchema}
            triggerType={triggerType}
            showTypePicker
          />
        </CardContent>
      </Card>
    </div>
  );
}
