import type { FlowNode, ActionNode, ConditionNode, DelayNode, JsonSchema } from '@/lib/api';
import { FlowStepEditor } from '@/components/flow-step-editor';
import { ConditionStepEditor } from '@/components/flow-editor/condition-step-editor';
import { DelayStepEditor } from '@/components/flow-editor/delay-step-editor';
import { StepTypePicker, type StepType } from '@/components/flow-editor/step-type-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ArrowUp, ArrowDown, Trash2, Plus, GitBranch, Clock } from 'lucide-react';
import { generateNodeId } from '@/lib/utils';

interface FlowStepListProps {
  steps: FlowNode[];
  onChange: (steps: FlowNode[]) => void;
  payloadSchema?: JsonSchema;
  depth?: number;
  showTypePicker?: boolean;
}

const MAX_DEPTH = 3;

export function createNode(type: StepType): FlowNode {
  const id = generateNodeId();
  switch (type) {
    case 'action':
      return { id, type: 'action', action: '', config: {} } satisfies ActionNode;
    case 'condition':
      return { id, type: 'condition', expression: '', then: [], else: [] } satisfies ConditionNode;
    case 'delay':
      return { id, type: 'delay', duration: 300, then: [] } satisfies DelayNode;
    default: {
      const _exhaustive: never = type;
      return _exhaustive;
    }
  }
}

export function FlowStepList({ steps, onChange, payloadSchema, depth = 0, showTypePicker = true }: FlowStepListProps) {
  const addStep = (type: StepType) => {
    onChange([...steps, createNode(type)]);
  };

  const addActionStep = () => addStep('action');

  const updateStep = (index: number, step: FlowNode) => {
    const next = [...steps];
    next[index] = step;
    onChange(next);
  };

  const removeStep = (index: number) => {
    onChange(steps.filter((_, i) => i !== index));
  };

  const moveStep = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= steps.length) return;
    const next = [...steps];
    [next[index], next[target]] = [next[target], next[index]];
    onChange(next);
  };

  const stepLabel = (step: FlowNode) => {
    switch (step.type) {
      case 'action': return 'Action';
      case 'condition': return <span className="flex items-center gap-1"><GitBranch className="h-3 w-3" /> Condition</span>;
      case 'delay': return <span className="flex items-center gap-1"><Clock className="h-3 w-3" /> Delay</span>;
    }
  };

  const atMaxDepth = depth >= MAX_DEPTH;

  return (
    <div className="space-y-3">
      {steps.length === 0 && (
        <p className="py-4 text-center text-sm text-muted-foreground">
          No steps yet. Add a step to get started.
        </p>
      )}

      {steps.map((step, i) => (
        <Card key={step.id} className="relative">
          <CardContent className="pt-4">
            <div className="mb-3 flex items-center justify-between">
              <span className="text-xs font-medium text-muted-foreground">
                {stepLabel(step)} {depth === 0 ? `— Step ${i + 1}` : ''}
              </span>
              <div className="flex items-center gap-1">
                <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => moveStep(i, -1)} disabled={i === 0}>
                  <ArrowUp className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={() => moveStep(i, 1)} disabled={i === steps.length - 1}>
                  <ArrowDown className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="sm" className="h-7 w-7 p-0 text-destructive hover:text-destructive" onClick={() => removeStep(i)}>
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>

            {step.type === 'action' && (
              <FlowStepEditor
                step={step}
                stepIndex={i}
                onChange={(updated) => updateStep(i, updated)}
                payloadSchema={payloadSchema}
              />
            )}

            {step.type === 'condition' && (
              <ConditionStepEditor
                step={step}
                onChange={(updated) => updateStep(i, updated)}
                payloadSchema={payloadSchema}
                depth={depth}
              />
            )}

            {step.type === 'delay' && (
              <DelayStepEditor
                step={step}
                onChange={(updated) => updateStep(i, updated)}
                payloadSchema={payloadSchema}
                depth={depth}
              />
            )}
          </CardContent>
        </Card>
      ))}

      {!atMaxDepth && (
        showTypePicker ? (
          <StepTypePicker onSelect={addStep} />
        ) : (
          <Button variant="outline" size="sm" onClick={addActionStep} className="w-full">
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            Add Step
          </Button>
        )
      )}
    </div>
  );
}
