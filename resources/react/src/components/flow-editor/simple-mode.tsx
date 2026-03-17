import { useRef } from 'react';
import type { ActionNode, FlowNode, JsonSchema } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TriggerSelector } from '@/components/trigger-selector';
import { FlowStepEditor } from '@/components/flow-step-editor';
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
}: SimpleModeProps) {
  // Stable default so we don't generate a new ID every render
  const defaultStep = useRef<ActionNode>({
    id: generateNodeId(),
    type: 'action',
    action: '',
    config: {},
  });
  const actionStep = (steps.find((s) => s.type === 'action') as ActionNode) ?? defaultStep.current;

  const handleActionChange = (updated: ActionNode) => {
    // Replace the first action step (or add it)
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
        {/* Trigger card */}
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">When this happens</CardTitle>
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

        {/* Arrow connector */}
        <div className="hidden md:flex items-center justify-center pt-16">
          <ArrowRight className="h-5 w-5 text-muted-foreground" />
        </div>
        <div className="flex md:hidden items-center justify-center">
          <div className="h-6 w-px bg-border" />
        </div>

        {/* Action card */}
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
