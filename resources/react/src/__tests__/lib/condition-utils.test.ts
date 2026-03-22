import { describe, it, expect } from 'vitest';
import {
  getOperatorsForType,
  getDefaultOperator,
  rulesToExpression,
} from '@/lib/condition-utils';
import type { ConditionRule } from '@/lib/api';

describe('getOperatorsForType', () => {
  it('returns array operators for array type', () => {
    const ops = getOperatorsForType('array');
    const values = ops.map((o) => o.value);
    expect(values).toContain('has');
    expect(values).toContain('does_not_have');
    expect(values).toContain('is_empty');
    expect(values).toContain('is_not_empty');
  });

  it('excludes equals/not_equals from array operators', () => {
    const ops = getOperatorsForType('array');
    const values = ops.map((o) => o.value);
    expect(values).not.toContain('equals');
    expect(values).not.toContain('not_equals');
    expect(values).not.toContain('contains');
  });

  it('excludes array operators from string type', () => {
    const ops = getOperatorsForType('string');
    const values = ops.map((o) => o.value);
    expect(values).not.toContain('has');
    expect(values).not.toContain('does_not_have');
    expect(values).toContain('equals');
    expect(values).toContain('contains');
  });

  it('returns number and any operators for integer type', () => {
    const ops = getOperatorsForType('integer');
    const values = ops.map((o) => o.value);
    expect(values).toContain('equals');
    expect(values).toContain('not_equals');
    expect(values).toContain('greater_than');
    expect(values).toContain('less_than');
    expect(values).toContain('is_empty');
    expect(values).toContain('is_not_empty');
    expect(values).not.toContain('contains');
    expect(values).not.toContain('has');
  });
});

describe('getDefaultOperator', () => {
  it('returns has for array type', () => {
    expect(getDefaultOperator('array')).toBe('has');
  });

  it('returns equals for string type', () => {
    expect(getDefaultOperator('string')).toBe('equals');
  });

  it('returns equals for integer type', () => {
    expect(getDefaultOperator('integer')).toBe('equals');
  });
});

describe('rulesToExpression', () => {
  it('generates string equals with quotes', () => {
    const rules: ConditionRule[] = [{ field: 'status', operator: 'equals', value: 'active' }];
    expect(rulesToExpression(rules)).toBe('status == "active"');
  });

  it('generates numeric equals without quotes when resolver returns integer', () => {
    const rules: ConditionRule[] = [{ field: 'order.id', operator: 'equals', value: '1001' }];
    const resolver = (path: string) => (path === 'order.id' ? 'integer' : undefined);
    expect(rulesToExpression(rules, resolver)).toBe('order["id"] == 1001');
  });

  it('generates numeric not_equals without quotes', () => {
    const rules: ConditionRule[] = [{ field: 'order.id', operator: 'not_equals', value: '42' }];
    const resolver = (path: string) => (path === 'order.id' ? 'integer' : undefined);
    expect(rulesToExpression(rules, resolver)).toBe('order["id"] != 42');
  });

  it('falls back to quoted string when numeric value is not a number', () => {
    const rules: ConditionRule[] = [{ field: 'order.id', operator: 'equals', value: 'abc' }];
    const resolver = (path: string) => (path === 'order.id' ? 'integer' : undefined);
    expect(rulesToExpression(rules, resolver)).toBe('order["id"] == "abc"');
  });

  it('generates array has expression', () => {
    const rules: ConditionRule[] = [{ field: 'user.roles', operator: 'has', value: 'admin' }];
    expect(rulesToExpression(rules)).toBe('"admin" in user["roles"]');
  });

  it('generates array does_not_have expression', () => {
    const rules: ConditionRule[] = [{ field: 'user.roles', operator: 'does_not_have', value: 'admin' }];
    expect(rulesToExpression(rules)).toBe('"admin" not in user["roles"]');
  });

  it('joins multiple rules with and', () => {
    const rules: ConditionRule[] = [
      { field: 'status', operator: 'equals', value: 'active' },
      { field: 'user.roles', operator: 'has', value: 'admin' },
    ];
    expect(rulesToExpression(rules)).toBe('status == "active" and "admin" in user["roles"]');
  });

  it('skips rules with empty field', () => {
    const rules: ConditionRule[] = [
      { field: '', operator: 'equals', value: 'test' },
      { field: 'status', operator: 'equals', value: 'active' },
    ];
    expect(rulesToExpression(rules)).toBe('status == "active"');
  });

  it('returns empty string for no valid rules', () => {
    expect(rulesToExpression([])).toBe('');
    expect(rulesToExpression([{ field: '', operator: 'equals', value: '' }])).toBe('');
  });

  it('generates is_empty expression', () => {
    const rules: ConditionRule[] = [{ field: 'user.roles', operator: 'is_empty', value: '' }];
    expect(rulesToExpression(rules)).toBe('(user["roles"] == "" or user["roles"] == null)');
  });
});
