import { useState } from 'react';
import type { FlowNode, JsonSchema } from '@/lib/api';
import { createNode, type StepType } from '@/lib/flow-utils';
import { useActions } from '@/hooks/use-actions';
import { StepCard } from './step-card';
import { StepAdder } from './step-adder';
import { StepConnector } from './step-connector';

interface SentenceStepListProps {
  steps: FlowNode[];
  onChange: (steps: FlowNode[]) => void;
  payloadSchema?: JsonSchema;
  triggerType?: string;
  sampleData?: Record<string, unknown>;
}

export function SentenceStepList({
  steps,
  onChange,
  payloadSchema,
  triggerType,
  sampleData,
}: SentenceStepListProps) {
  const { actions } = useActions();
  const [expandedStepId, setExpandedStepId] = useState<string | null>(null);

  const addStep = (type: StepType) => {
    const node = createNode(type);
    onChange([...steps, node]);
    setExpandedStepId(node.id);
  };

  const updateStep = (index: number, step: FlowNode) => {
    const next = [...steps];
    next[index] = step;
    onChange(next);
  };

  const removeStep = (index: number) => {
    const step = steps[index];
    if (expandedStepId === step.id) setExpandedStepId(null);
    onChange(steps.filter((_, i) => i !== index));
  };

  if (steps.length === 0) {
    return (
      <div className="py-2">
        <StepAdder onAdd={addStep} />
      </div>
    );
  }

  return (
    <div className="space-y-0">
      {steps.map((step, i) => (
        <div key={step.id}>
          <StepCard
            step={step}
            index={i}
            totalSteps={steps.length}
            isExpanded={expandedStepId === step.id}
            onToggle={() => setExpandedStepId(expandedStepId === step.id ? null : step.id)}
            onDelete={() => removeStep(i)}
            onChange={(updated) => updateStep(i, updated)}
            actions={actions}
            payloadSchema={payloadSchema}
            triggerType={triggerType}
            sampleData={sampleData}
          />
          {i < steps.length - 1 && <StepConnector />}
        </div>
      ))}

      <StepConnector />
      <StepAdder onAdd={addStep} />
    </div>
  );
}
