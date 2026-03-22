import type { JsonSchema, JsonSchemaProperty, ConditionRule } from '@/lib/api';
import { getConfig } from '@/lib/api';
import { formatLabel } from '@/lib/constants';

export type { ConditionRule };

export interface OperatorDef {
  value: string;
  label: string;
  category: 'string' | 'number' | 'array' | 'any';
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
  { value: 'has', label: 'has', category: 'array' },
  { value: 'does_not_have', label: 'does not have', category: 'array' },
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
  if (type === 'array') {
    return OPERATORS.filter((o) => o.category === 'array' || o.value === 'is_empty' || o.value === 'is_not_empty');
  }
  if (type === 'integer' || type === 'number') {
    return OPERATORS.filter((o) => o.category === 'number' || o.category === 'any');
  }
  return OPERATORS.filter((o) => o.category !== 'array');
}

/** Get the default operator for a given field type. */
export function getDefaultOperator(type: string): string {
  return type === 'array' ? 'has' : 'equals';
}

/** Escape a value for safe use inside a regex pattern. */
function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
}

/** Convert a dot-path field name to ExpressionLanguage syntax. */
function fieldToExpr(field: string): string {
  const parts = field.split('.');
  if (parts.length === 1) return parts[0];
  return parts[0] + parts.slice(1).map((p) => `["${p}"]`).join('');
}

/** Convert a single rule to an ExpressionLanguage expression fragment. */
function ruleToExpr(rule: ConditionRule, fieldType?: string): string {
  const field = fieldToExpr(rule.field);
  const val = rule.value;
  const isNumeric = (fieldType === 'integer' || fieldType === 'number') && !isNaN(Number(val)) && val !== '';

  switch (rule.operator) {
    case 'equals':
      return isNumeric ? `${field} == ${val}` : `${field} == "${val}"`;
    case 'not_equals':
      return isNumeric ? `${field} != ${val}` : `${field} != "${val}"`;
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
    case 'has':
      return `"${val}" in ${field}`;
    case 'does_not_have':
      return `"${val}" not in ${field}`;
    default:
      return `${field} == "${val}"`;
  }
}

/** Convert an array of rules to an ExpressionLanguage expression (AND-joined). */
export function rulesToExpression(
  rules: ConditionRule[],
  typeResolver?: (fieldPath: string) => string | undefined,
): string {
  const valid = rules.filter((r) => r.field);
  if (valid.length === 0) return '';
  return valid.map((r) => ruleToExpr(r, typeResolver?.(r.field))).join(' and ');
}

/** Get the human-readable label for an operator value. */
export function getOperatorLabel(op: string): string {
  return OPERATORS.find((o) => o.value === op)?.label ?? op;
}

/** Create an empty condition rule. */
export function createEmptyRule(): ConditionRule {
  return { field: '', operator: 'equals', value: '' };
}

/** Resolve a schema property by dot-path (e.g. "user.roles"). */
export function resolveFieldProperty(schema: JsonSchema, fieldPath: string): JsonSchemaProperty | undefined {
  const parts = fieldPath.split('.');
  let properties = schema.properties;
  for (let i = 0; i < parts.length; i++) {
    if (!properties) return undefined;
    const prop = properties[parts[i]];
    if (!prop) return undefined;
    if (i === parts.length - 1) return prop;
    if (prop.type === 'object' && prop.properties) {
      properties = prop.properties;
    } else {
      return undefined;
    }
  }
  return undefined;
}

/** Map enum values to { value, label } options using enumLabels or formatLabel fallback. */
export function formatEnumOptions(
  enumValues: string[],
  enumLabels?: Record<string, string>,
): { value: string; label: string }[] {
  return enumValues.map((e) => ({
    value: e,
    label: enumLabels?.[e] ?? formatLabel(e),
  }));
}

export type ValueOptions =
  | { type: 'enum'; options: { value: string; label: string }[] }
  | { type: 'dynamic'; url: string }
  | null;

const ROLE_FIELD_PATTERN = /^roles?$/;

/** Resolve dropdown options for a condition field from filter_schema, merged schema, or WordPress roles. */
export function getConditionValueOptions(
  field: string,
  filterSchema: Record<string, JsonSchemaProperty> | undefined,
  merged: JsonSchema,
  triggerType?: string,
): ValueOptions {
  // 1. Check trigger's filter_schema
  const filterField = filterSchema?.[field];
  if (filterField?.enum && filterField.enum.length > 0) {
    return { type: 'enum', options: formatEnumOptions(filterField.enum, filterField.enumLabels) };
  }
  if (filterField?.dynamic && triggerType) {
    return { type: 'dynamic', url: `triggers/${triggerType}/filter-options/${field}` };
  }

  // 2. Check merged payload schema property
  const mergedProp = resolveFieldProperty(merged, field);
  if (mergedProp?.enum && mergedProp.enum.length > 0) {
    return { type: 'enum', options: formatEnumOptions(mergedProp.enum, mergedProp.enumLabels) };
  }

  // 3. Role fields — use WordPress roles from config
  const leafName = field.split('.').pop();
  if (leafName && ROLE_FIELD_PATTERN.test(leafName)) {
    const roles = getConfig().roles;
    if (roles && Object.keys(roles).length > 0) {
      return {
        type: 'enum',
        options: Object.entries(roles).map(([value, label]) => ({ value, label })),
      };
    }
  }

  return null;
}
