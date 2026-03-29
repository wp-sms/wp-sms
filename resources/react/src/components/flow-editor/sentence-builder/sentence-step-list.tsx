import { __ } from '@wordpress/i18n';
import { useState, useMemo } from 'react';
import type { FlowNode, JsonSchema, JsonSchemaProperty } from '@/lib/api';
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

  // Compute per-step enriched payload schemas that include previous action outputs.
  // This lets downstream variable pickers show {{actions.<action_id>.<field>}} without changes.
  const stepSchemas = useMemo(() => {
    const actionMap = new Map(actions.map((a) => [a.id, a]));
    const accumulated: Record<string, JsonSchemaProperty> = {};
    let hasOutputs = false;

    return steps.map((step, index) => {
      if (index === 0) return payloadSchema;

      // Accumulate the previous step's output (index - 1) into the running map
      const prev = steps[index - 1];
      if (prev.type === 'action' && prev.action) {
        const def = actionMap.get(prev.action);
        if (def?.output_schema?.properties && Object.keys(def.output_schema.properties).length) {
          accumulated[prev.action] = {
            type: 'object',
            title: def.name,
            properties: def.output_schema.properties,
          };
          hasOutputs = true;
        }
      }

      if (!hasOutputs) return payloadSchema;

      return {
        ...payloadSchema,
        type: payloadSchema?.type ?? 'object',
        properties: {
          ...payloadSchema?.properties,
          actions: {
            type: 'object' as const,
            title: __('Previous Actions', 'wp-sms'),
            properties: { ...accumulated },
          },
        },
      } satisfies JsonSchema;
    });
  }, [steps, actions, payloadSchema]);

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
            payloadSchema={stepSchemas[i]}
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
