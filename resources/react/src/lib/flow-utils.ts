import { __, sprintf } from '@wordpress/i18n';
import type { FlowNode, ActionNode, ConditionNode, DelayNode, ActionDefinition, JsonSchema, JsonSchemaProperty } from '@/lib/api';
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
  action:    { border: 'border-s-blue-400',   iconBg: 'bg-blue-100',   iconFg: 'text-blue-600'   },
  condition: { border: 'border-s-purple-400', iconBg: 'bg-purple-100', iconFg: 'text-purple-600' },
  delay:     { border: 'border-s-amber-400',  iconBg: 'bg-amber-100',  iconFg: 'text-amber-600'  },
} as const;

export const STEP_LABELS = {
  action: __('Action', 'wp-sms'),
  condition: __('Condition', 'wp-sms'),
  delay: __('Delay', 'wp-sms'),
} as const;

export const STATUS_DOT = {
  ready:       { color: 'bg-emerald-400', label: __('Ready', 'wp-sms') },
  needs_setup: { color: 'bg-amber-400',   label: __('Needs setup', 'wp-sms') },
  error:       { color: 'bg-red-400',     label: __('Error', 'wp-sms') },
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
      return __('Unknown step', 'wp-sms');
  }
}

function getActionSummary(step: ActionNode, actions?: ActionDefinition[]): string {
  if (!step.action) return __('No action selected', 'wp-sms');
  const action = actions?.find((a) => a.id === step.action);
  const name = action?.name ?? step.action;

  const to = step.config.to as string | undefined;
  if (to) return sprintf(__('%1$s to %2$s', 'wp-sms'), name, truncate(to, 40));
  return name;
}

function getConditionSummary(step: ConditionNode): string {
  if (step.rules && step.rules.length > 0) {
    const first = step.rules[0];
    if (first.field) {
      const fieldLabel = first.field.split('.').pop() ?? first.field;
      return sprintf(__('If %1$s %2$s %3$s', 'wp-sms'), fieldLabel, getOperatorLabel(first.operator), first.value || '').trim();
    }
  }
  if (step.expression) {
    return sprintf(__('If %s', 'wp-sms'), truncate(step.expression, 50));
  }
  return __('No condition set', 'wp-sms');
}

function getDelaySummary(step: DelayNode): string {
  if (!step.duration) return __('No delay set', 'wp-sms');
  return sprintf(__('Wait %s', 'wp-sms'), formatDuration(step.duration));
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
          errors.push(sprintf(__('%1$s: missing required field(s) %2$s', 'wp-sms'), name, labels.join(', ')));
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

// --- System & Entity Variable Schemas ---
// Keep in sync with PayloadResolver::getSystemVariables() in src/Flow/Engine/PayloadResolver.php

export const SYSTEM_VARIABLE_SCHEMA: JsonSchema = {
  type: 'object',
  properties: {
    site: {
      type: 'object',
      title: 'Site',
      properties: {
        name:      { type: 'string', title: 'Site Name', example: 'My Website' },
        url:       { type: 'string', title: 'Site URL', example: 'https://example.com' },
        email:     { type: 'string', title: 'Admin Email', example: 'admin@example.com' },
        phone:     { type: 'string', title: 'Site Phone', example: '+1234567890' },
        login_url: { type: 'string', title: 'Login URL', example: 'https://example.com/wp-login.php' },
        admin_url: { type: 'string', title: 'Admin URL', example: 'https://example.com/wp-admin/' },
        tagline:   { type: 'string', title: 'Tagline', example: 'Just another WordPress site' },
      },
    },
    now: {
      type: 'object',
      title: 'Date & Time',
      properties: {
        date:       { type: 'string', title: 'Current Date', example: '2026-03-18' },
        time:       { type: 'string', title: 'Current Time', example: '14:30' },
        datetime:   { type: 'string', title: 'Current Date & Time', example: '2026-03-18 14:30:00' },
        year:       { type: 'string', title: 'Current Year', example: '2026' },
        day_name:   { type: 'string', title: 'Day Name', example: 'Monday' },
        month_name: { type: 'string', title: 'Month Name', example: 'March' },
        day:        { type: 'string', title: 'Day of Month', example: '20' },
        month:      { type: 'string', title: 'Month Number', example: '03' },
        hour:       { type: 'string', title: 'Current Hour', example: '14' },
      },
    },
  },
};

// Keep in sync with PayloadSchemas::extractWpUser() in src/Integration/PayloadSchemas.php
export const USER_SCHEMA: JsonSchemaProperty = {
  type: 'object',
  title: 'User',
  properties: {
    email:        { type: 'string', title: 'Email', example: 'user@example.com' },
    phone:        { type: 'string', title: 'Phone', example: '+1234567890' },
    login:        { type: 'string', title: 'Username', example: 'johndoe' },
    display_name: { type: 'string', title: 'Display Name', example: 'John Doe' },
    first_name:   { type: 'string', title: 'First Name', example: 'John' },
    last_name:    { type: 'string', title: 'Last Name', example: 'Doe' },
    roles:        { type: 'array', title: 'Roles', example: ['subscriber'] },
  },
};

export const AUTHOR_SCHEMA: JsonSchemaProperty = {
  type: 'object',
  title: 'Author',
  properties: {
    email:        { type: 'string', title: 'Email', example: 'author@example.com' },
    phone:        { type: 'string', title: 'Phone', example: '+1234567890' },
    display_name: { type: 'string', title: 'Display Name', example: 'Jane Doe' },
  },
};

// Keep in sync with PayloadSchemas::extractContact() in src/Integration/PayloadSchemas.php
export const CONTACT_SCHEMA: JsonSchemaProperty = {
  type: 'object',
  title: 'Contact',
  properties: {
    email:      { type: 'string', title: 'Email', example: 'contact@example.com' },
    phone:      { type: 'string', title: 'Phone', example: '+1234567890' },
    first_name: { type: 'string', title: 'First Name', example: 'John' },
    last_name:  { type: 'string', title: 'Last Name', example: 'Doe' },
    status:     {
      type: 'string',
      title: 'Status',
      example: 'subscribed',
      enum: ['subscribed', 'bounced', 'complained'],
      enumLabels: {
        subscribed: 'Subscribed',
        bounced: 'Bounced',
        complained: 'Complained',
      },
    },
    source:     { type: 'string', title: 'Source', example: 'form' },
  },
};

// Keep in sync with PayloadSchemas::extractPost() in src/Integration/PayloadSchemas.php
export const POST_SCHEMA: JsonSchemaProperty = {
  type: 'object',
  title: 'Post',
  properties: {
    title:   { type: 'string', title: 'Title', example: 'My Post' },
    url:     { type: 'string', title: 'URL', example: 'https://example.com/my-post' },
    excerpt: { type: 'string', title: 'Excerpt', example: 'Post summary...' },
    type:    { type: 'string', title: 'Type', example: 'post' },
    status:  { type: 'string', title: 'Status', example: 'publish' },
    date:    { type: 'string', title: 'Date', example: '2026-03-18 14:30:00' },
  },
};

/** Build a merged schema combining system vars, trigger payload, and auto-expanded entities. */
export function buildMergedSchema(payloadSchema?: JsonSchema): JsonSchema {
  const properties: Record<string, JsonSchemaProperty> = {
    ...SYSTEM_VARIABLE_SCHEMA.properties,
  };

  if (payloadSchema?.properties) {
    Object.assign(properties, payloadSchema.properties);

    // Auto-expand entity schemas for ID fields when object key is missing
    if (payloadSchema.properties.user_id && !payloadSchema.properties.user) {
      properties.user = USER_SCHEMA;
    }
    if (payloadSchema.properties.author_id && !payloadSchema.properties.author) {
      properties.author = AUTHOR_SCHEMA;
    }
    if (payloadSchema.properties.post_id && !payloadSchema.properties.post) {
      properties.post = POST_SCHEMA;
    }
    if (payloadSchema.properties.contact_id && !payloadSchema.properties.contact) {
      properties.contact = CONTACT_SCHEMA;
    }
  }

  return { type: 'object', properties };
}
