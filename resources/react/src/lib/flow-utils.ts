import type { FlowNode, ActionNode, ConditionNode, DelayNode, ActionDefinition } from '@/lib/api';
import { getOperatorLabel } from '@/lib/condition-utils';

export type StepStatus = 'needs_setup' | 'ready' | 'error';

/** Get a one-line summary for a step card. */
export function getStepSummary(step: FlowNode, actions?: ActionDefinition[]): string {
  switch (step.type) {
    case 'action':
      return getActionSummary(step, actions);
    case 'condition':
      return getConditionSummary(step);
    case 'delay':
      return getDelaySummary(step);
    default:
      return 'Unknown step';
  }
}

function getActionSummary(step: ActionNode, actions?: ActionDefinition[]): string {
  if (!step.action) return 'No action selected';
  const action = actions?.find((a) => a.id === step.action);
  const name = action?.name ?? step.action;

  const to = step.config.to as string | undefined;
  if (to) return `${name} to ${truncate(to, 40)}`;
  return name;
}

function getConditionSummary(step: ConditionNode): string {
  if (step.rules && step.rules.length > 0) {
    const first = step.rules[0];
    if (first.field) {
      const fieldLabel = first.field.split('.').pop() ?? first.field;
      return `If ${fieldLabel} ${getOperatorLabel(first.operator)} ${first.value || ''}`.trim();
    }
  }
  if (step.expression) {
    return `If ${truncate(step.expression, 50)}`;
  }
  return 'No condition set';
}

function getDelaySummary(step: DelayNode): string {
  if (!step.duration) return 'No delay set';
  return `Wait ${formatDuration(step.duration)}`;
}

function formatDuration(seconds: number): string {
  if (seconds >= 86400 && seconds % 86400 === 0) return `${seconds / 86400} day${seconds / 86400 !== 1 ? 's' : ''}`;
  if (seconds >= 3600 && seconds % 3600 === 0) return `${seconds / 3600} hour${seconds / 3600 !== 1 ? 's' : ''}`;
  if (seconds >= 60 && seconds % 60 === 0) return `${seconds / 60} minute${seconds / 60 !== 1 ? 's' : ''}`;
  return `${seconds} second${seconds !== 1 ? 's' : ''}`;
}

function truncate(str: string, max: number): string {
  return str.length > max ? str.slice(0, max) + '...' : str;
}

/** Get the validation status of a step. */
export function getStepStatus(step: FlowNode): StepStatus {
  switch (step.type) {
    case 'action':
      if (!step.action) return 'needs_setup';
      return 'ready';
    case 'condition': {
      if (step.rules && step.rules.length > 0) {
        return step.rules.some((r) => r.field) ? 'ready' : 'needs_setup';
      }
      return step.expression ? 'ready' : 'needs_setup';
    }
    case 'delay':
      return step.duration > 0 ? 'ready' : 'needs_setup';
    default:
      return 'needs_setup';
  }
}

/** Recursively count all steps including nested ones in condition/delay branches. */
export function countSteps(steps: FlowNode[]): number {
  let count = 0;
  for (const step of steps) {
    count++;
    if (step.type === 'condition') {
      count += countSteps(step.then) + countSteps(step.else);
    } else if (step.type === 'delay') {
      count += countSteps(step.then);
    }
  }
  return count;
}
