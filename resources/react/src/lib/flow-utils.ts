import type { FlowNode, ActionNode, ConditionNode, DelayNode, ActionDefinition } from '@/lib/api';
import { getOperatorLabel } from '@/lib/condition-utils';

export type StepStatus = 'needs_setup' | 'ready' | 'error';

export const STEP_COLORS = {
  action:    { border: 'border-l-blue-400',   iconBg: 'bg-blue-100',   iconFg: 'text-blue-600'   },
  condition: { border: 'border-l-purple-400', iconBg: 'bg-purple-100', iconFg: 'text-purple-600' },
  delay:     { border: 'border-l-amber-400',  iconBg: 'bg-amber-100',  iconFg: 'text-amber-600'  },
} as const;

export const STATUS_DOT = {
  ready:       { color: 'bg-emerald-400', label: 'Ready' },
  needs_setup: { color: 'bg-amber-400',   label: 'Needs setup' },
  error:       { color: 'bg-red-400',     label: 'Error' },
} as const;

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

/** Validate that all required config fields are filled for action steps. */
export function validateFlowSteps(
  steps: FlowNode[],
  actions: ActionDefinition[],
): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  const actionMap = new Map(actions.map((a) => [a.id, a]));

  function walk(nodes: FlowNode[]) {
    for (const node of nodes) {
      if (node.type === 'action' && node.action) {
        const def = actionMap.get(node.action);
        const required = def?.config_schema?.required;
        if (required && required.length > 0) {
          const missing = required.filter((field) => {
            const val = node.config[field];
            return val === undefined || val === null || val === '';
          });
          if (missing.length > 0) {
            const name = def?.name ?? node.action;
            const labels = missing.map((f) => {
              const prop = def?.config_schema?.properties?.[f];
              return prop?.title ?? f;
            });
            errors.push(`${name}: missing required field${labels.length > 1 ? 's' : ''} ${labels.join(', ')}`);
          }
        }
      } else if (node.type === 'condition') {
        walk(node.then);
        walk(node.else);
      } else if (node.type === 'delay') {
        walk(node.then);
      }
    }
  }

  walk(steps);
  return { valid: errors.length === 0, errors };
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
