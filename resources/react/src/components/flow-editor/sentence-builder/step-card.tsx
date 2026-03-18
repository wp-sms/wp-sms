import type { FlowNode, ActionDefinition, JsonSchema } from '@/lib/api';
import { getStepSummary, getStepStatus, STEP_COLORS, STEP_LABELS, STATUS_DOT } from '@/lib/flow-utils';
import { CollapsibleCard } from './collapsible-card';
import { ActionSentence } from './action-sentence';
import { ConditionSentence } from './condition-sentence';
import { DelaySentence } from './delay-sentence';
import { Zap, GitBranch, Clock } from 'lucide-react';

export const STEP_ICONS = {
  action: Zap,
  condition: GitBranch,
  delay: Clock,
} as const;

interface StepCardProps {
  step: FlowNode;
  index: number;
  totalSteps: number;
  isExpanded: boolean;
  onToggle: () => void;
  onDelete: () => void;
  onChange: (step: FlowNode) => void;
  actions: ActionDefinition[];
  payloadSchema?: JsonSchema;
  triggerType?: string;
  sampleData?: Record<string, unknown>;
}

export function StepCard({
  step,
  index,
  totalSteps,
  isExpanded,
  onToggle,
  onDelete,
  onChange,
  actions,
  payloadSchema,
  triggerType,
  sampleData,
}: StepCardProps) {
  const colors = STEP_COLORS[step.type];
  const status = getStepStatus(step);
  const statusDot = STATUS_DOT[status];

  return (
    <CollapsibleCard
      icon={STEP_ICONS[step.type]}
      iconBg={colors.iconBg}
      iconFg={colors.iconFg}
      borderColor={colors.border}
      label={STEP_LABELS[step.type]}
      summary={getStepSummary(step, actions)}
      statusColor={statusDot.color}
      statusLabel={statusDot.label}
      isExpanded={isExpanded}
      onToggle={onToggle}
      onDelete={onDelete}
      number={totalSteps > 1 ? index + 1 : undefined}
    >
      {step.type === 'action' && (
        <ActionSentence
          step={step}
          onChange={onChange}
          payloadSchema={payloadSchema}
          triggerType={triggerType}
          sampleData={sampleData}
        />
      )}
      {step.type === 'condition' && (
        <ConditionSentence
          step={step}
          onChange={onChange}
          payloadSchema={payloadSchema}
          triggerType={triggerType}
        />
      )}
      {step.type === 'delay' && (
        <DelaySentence
          step={step}
          onChange={onChange}
        />
      )}
    </CollapsibleCard>
  );
}
