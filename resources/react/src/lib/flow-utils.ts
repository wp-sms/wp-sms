import type { FlowNode, ActionNode, ConditionNode, DelayNode, ActionDefinition } from '@/lib/api';
import { getOperatorLabel } from '@/lib/condition-utils';
import { generateNodeId } from '@/lib/utils';

export type StepType = 'action' | 'condition' | 'delay';

export type StepStatus = 'needs_setup' | 'ready' | 'error';

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

export const STEP_COLORS = {
  action:    { border: 'border-l-blue-400',   iconBg: 'bg-blue-100',   iconFg: 'text-blue-600'   },
  condition: { border: 'border-l-purple-400', iconBg: 'bg-purple-100', iconFg: 'text-purple-600' },
  delay:     { border: 'border-l-amber-400',  iconBg: 'bg-amber-100',  iconFg: 'text-amber-600'  },
} as const;

export const STEP_LABELS = {
  action: 'Action',
  condition: 'Condition',
  delay: 'Delay',
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

/** Validate that all required config fields are filled for action steps (flat list). */
export function validateFlowSteps(
  steps: FlowNode[],
  actions: ActionDefinition[],
): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  const actionMap = new Map(actions.map((a) => [a.id, a]));

  for (const node of steps) {
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
    }
  }

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

/** Count all steps in a tree (flattens nested branches). */
export function countSteps(steps: FlowNode[]): number {
  return flattenSteps(steps).length;
}

/**
 * Flatten a nested step tree into a flat sequential list.
 * Walks depth-first: for condition/delay nodes, push the node (with empty children)
 * then recurse into `node.then`. Condition `else` branches are dropped.
 */
export function flattenSteps(nested: FlowNode[]): FlowNode[] {
  const result: FlowNode[] = [];
  for (const node of nested) {
    if (node.type === 'condition') {
      result.push({ ...node, then: [], else: [] });
      result.push(...flattenSteps(node.then));
    } else if (node.type === 'delay') {
      result.push({ ...node, then: [] });
      result.push(...flattenSteps(node.then));
    } else {
      result.push(node);
    }
  }
  return result;
}

/**
 * Nest a flat sequential list into the tree structure the backend expects.
 * Processes right-to-left, building an accumulator:
 * - Action: prepend to accumulator
 * - Condition: wrap entire accumulator into condition.then, set else: []
 * - Delay: wrap entire accumulator into delay.then
 */
export function nestSteps(flat: FlowNode[]): FlowNode[] {
  let accumulator: FlowNode[] = [];
  for (let i = flat.length - 1; i >= 0; i--) {
    const node = flat[i];
    if (node.type === 'condition') {
      accumulator = [{ ...node, then: accumulator, else: [] }];
    } else if (node.type === 'delay') {
      accumulator = [{ ...node, then: accumulator }];
    } else {
      accumulator.unshift(node);
    }
  }
  return accumulator;
}
