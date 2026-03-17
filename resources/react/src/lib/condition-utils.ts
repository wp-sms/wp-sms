import type { JsonSchema, JsonSchemaProperty, ConditionRule } from '@/lib/api';

export type { ConditionRule };

export interface OperatorDef {
  value: string;
  label: string;
  category: 'string' | 'number' | 'any';
  hideValue?: boolean;
}

export const OPERATORS: OperatorDef[] = [
  { value: 'equals', label: 'is', category: 'any' },
  { value: 'not_equals', label: 'is not', category: 'any' },
  { value: 'contains', label: 'contains', category: 'string' },
  { value: 'not_contains', label: 'does not contain', category: 'string' },
  { value: 'starts_with', label: 'starts with', category: 'string' },
  { value: 'ends_with', label: 'ends with', category: 'string' },
  { value: 'is_empty', label: 'is empty', category: 'any', hideValue: true },
  { value: 'is_not_empty', label: 'is not empty', category: 'any', hideValue: true },
  { value: 'greater_than', label: 'is greater than', category: 'number' },
  { value: 'less_than', label: 'is less than', category: 'number' },
];

export interface FieldOption {
  path: string;
  label: string;
  type: string;
  example?: string;
  group?: string;
}

/** Flatten schema properties into a flat list of field options with human labels. */
export function flattenSchemaFields(
  properties: Record<string, JsonSchemaProperty>,
  prefix = '',
  group?: string,
): FieldOption[] {
  const result: FieldOption[] = [];
  for (const [key, prop] of Object.entries(properties)) {
    const fullPath = prefix ? `${prefix}.${key}` : key;
    if (prop.type === 'object' && prop.properties) {
      result.push(...flattenSchemaFields(prop.properties, fullPath, prop.title ?? key));
    } else if (prop.type === 'object') {
      // Dynamic object without properties — show as single entry with hint
      result.push({
        path: fullPath,
        label: prop.title ?? key,
        type: 'object',
        example: 'Use dot notation: {{' + fullPath + '.key}}',
        group,
      });
    } else {
      result.push({
        path: fullPath,
        label: prop.title ?? key,
        type: prop.type,
        example: prop.example != null ? String(prop.example) : undefined,
        group,
      });
    }
  }
  return result;
}

/** Get operators available for a given field type. */
export function getOperatorsForType(type: string): OperatorDef[] {
  if (type === 'integer' || type === 'number') {
    return OPERATORS.filter((o) => o.category === 'number' || o.category === 'any');
  }
  return OPERATORS;
}

/** Escape a value for safe use inside a regex pattern. */
function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\\/]/g, '\\$&');
}

/** Convert a dot-path field name to ExpressionLanguage syntax. */
function fieldToExpr(field: string): string {
  const parts = field.split('.');
  if (parts.length === 1) return parts[0];
  return parts[0] + parts.slice(1).map((p) => `["${p}"]`).join('');
}

/** Convert a single rule to an ExpressionLanguage expression fragment. */
function ruleToExpr(rule: ConditionRule): string {
  const field = fieldToExpr(rule.field);
  const val = rule.value;

  switch (rule.operator) {
    case 'equals':
      return `${field} == "${val}"`;
    case 'not_equals':
      return `${field} != "${val}"`;
    case 'contains':
      return `${field} matches "/${escapeRegex(val)}/i"`;
    case 'not_contains':
      return `not (${field} matches "/${escapeRegex(val)}/i")`;
    case 'starts_with':
      return `${field} matches "/^${escapeRegex(val)}/i"`;
    case 'ends_with':
      return `${field} matches "/${escapeRegex(val)}$/i"`;
    case 'is_empty':
      return `(${field} == "" or ${field} == null)`;
    case 'is_not_empty':
      return `(${field} != "" and ${field} != null)`;
    case 'greater_than':
      return `${field} > ${val}`;
    case 'less_than':
      return `${field} < ${val}`;
    default:
      return `${field} == "${val}"`;
  }
}

/** Convert an array of rules to an ExpressionLanguage expression (AND-joined). */
export function rulesToExpression(rules: ConditionRule[]): string {
  const valid = rules.filter((r) => r.field);
  if (valid.length === 0) return '';
  return valid.map(ruleToExpr).join(' and ');
}

/** Get the human-readable label for an operator value. */
export function getOperatorLabel(op: string): string {
  return OPERATORS.find((o) => o.value === op)?.label ?? op;
}

/** Create an empty condition rule. */
export function createEmptyRule(): ConditionRule {
  return { field: '', operator: 'equals', value: '' };
}
