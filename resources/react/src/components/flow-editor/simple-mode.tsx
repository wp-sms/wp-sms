import { useRef } from 'react';
import type { ActionNode, FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TriggerSelector } from '@/components/trigger-selector';
import { FlowStepEditor } from '@/components/flow-step-editor';
import { useTestTrigger, TestTriggerButton, SampleDataPreview } from './test-trigger';
import { ArrowRight } from 'lucide-react';
import { generateNodeId } from '@/lib/utils';

interface SimpleModeProps {
  triggerType: string;
  triggerConfig: Record<string, unknown>;
  steps: FlowNode[];
  onChangeTrigger: (type: string, config: Record<string, unknown>) => void;
  onChangeSteps: (steps: FlowNode[]) => void;
  onPayloadSchemaChange: (schema: JsonSchema | undefined) => void;
  payloadSchema?: JsonSchema;
  onSwitchToAdvanced: () => void;
  flowId?: string;
  sampleData?: Record<string, unknown>;
  onSampleDataChange?: (data: Record<string, unknown> | undefined) => void;
}

export function SimpleMode({
  triggerType,
  triggerConfig,
  steps,
  onChangeTrigger,
  onChangeSteps,
  onPayloadSchemaChange,
  payloadSchema,
  onSwitchToAdvanced,
  flowId,
  sampleData,
  onSampleDataChange,
}: SimpleModeProps) {
  const defaultStep = useRef<ActionNode>({
    id: generateNodeId(),
    type: 'action',
    action: '',
    config: {},
  });
  const actionStep = (steps.find((s) => s.type === 'action') as ActionNode) ?? defaultStep.current;

  const { testing, handleTest } = useTestTrigger({ flowId, triggerType, payloadSchema, onSampleDataChange });

  const handleActionChange = (updated: ActionNode) => {
    const existing = steps.findIndex((s) => s.type === 'action');
    if (existing >= 0) {
      const next = [...steps];
      next[existing] = updated;
      onChangeSteps(next);
    } else {
      onChangeSteps([updated]);
    }
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-[1fr,auto,1fr] md:items-start">
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm">When this happens</CardTitle>
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

        <div className="hidden md:flex items-center justify-center pt-16">
          <ArrowRight className="h-5 w-5 text-muted-foreground" />
        </div>
        <div className="flex md:hidden items-center justify-center">
          <div className="h-6 w-px bg-border" />
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Do this</CardTitle>
          </CardHeader>
          <CardContent>
            <FlowStepEditor
              step={actionStep}
              stepIndex={0}
              onChange={handleActionChange}
              payloadSchema={payloadSchema}
              triggerType={triggerType}
              sampleData={sampleData}
            />
          </CardContent>
        </Card>
      </div>

      <p className="text-center text-sm text-muted-foreground">
        Need more steps, conditions, or delays?{' '}
        <button
          type="button"
          className="text-primary underline-offset-4 hover:underline"
          onClick={onSwitchToAdvanced}
        >
          Switch to Advanced
        </button>
      </p>
    </div>
  );
}
