import { useMemo, useCallback } from 'react';
import type { ConditionNode, JsonSchema } from '@/lib/api';
import type { ConditionRule } from '@/lib/condition-utils';
import {
  flattenSchemaFields,
  getOperatorsForType,
  OPERATORS,
  createEmptyRule,
  rulesToExpression,
  getConditionValueOptions,
} from '@/lib/condition-utils';
import { buildMergedSchema } from '@/lib/flow-utils';
import { useTriggers } from '@/hooks/use-triggers';
import { SentenceToken } from './sentence-token';
import { X } from 'lucide-react';

interface ConditionSentenceProps {
  step: ConditionNode;
  onChange: (step: ConditionNode) => void;
  payloadSchema?: JsonSchema;
  triggerType?: string;
}

export function ConditionSentence({ step, onChange, payloadSchema, triggerType }: ConditionSentenceProps) {
  const rules: ConditionRule[] = step.rules ?? [];
  const { triggers } = useTriggers();

  const filterSchema = useMemo(() => {
    const trigger = triggers.find((t) => t.id === triggerType);
    return trigger?.filter_schema;
  }, [triggers, triggerType]);

  const merged = useMemo(() => buildMergedSchema(payloadSchema), [payloadSchema]);

  const fields = useMemo(() => {
    return merged.properties
      ? flattenSchemaFields(merged.properties)
      : [];
  }, [merged]);

  const fieldOptions = useMemo(() => {
    return fields.map((f) => ({ value: f.path, label: f.label, group: f.group }));
  }, [fields]);

  const fieldMap = useMemo(() => new Map(fields.map((f) => [f.path, f])), [fields]);

  const updateRules = (newRules: ConditionRule[]) => {
    onChange({
      ...step,
      rules: newRules,
      expression: rulesToExpression(newRules),
    });
  };

  const updateRule = (index: number, patch: Partial<ConditionRule>) => {
    const next = rules.map((r, i) => (i === index ? { ...r, ...patch } : r));
    updateRules(next);
  };

  const removeRule = (index: number) => {
    updateRules(rules.filter((_, i) => i !== index));
  };

  const addRule = () => {
    updateRules([...rules, createEmptyRule()]);
  };

  const getOperatorOptions = (fieldPath: string) => {
    const field = fieldMap.get(fieldPath);
    const fieldType = field?.type ?? 'string';
    return getOperatorsForType(fieldType).map((op) => ({ value: op.value, label: op.label }));
  };

  const currentOpHidesValue = (operator: string) => {
    return OPERATORS.find((o) => o.value === operator)?.hideValue ?? false;
  };

  const getValueOptions = useCallback(
    (field: string) => getConditionValueOptions(field, filterSchema, merged, triggerType),
    [filterSchema, triggerType, merged],
  );

  return (
    <div className="space-y-3">
      {/* Condition rules */}
      {rules.map((rule, index) => (
        <div key={index} className="flex items-center gap-1.5 flex-wrap">
          <span className="text-sm font-medium text-muted-foreground">
            {index === 0 ? 'Only if' : 'and'}
          </span>
          <SentenceToken
            mode="select"
            value={rule.field}
            options={fieldOptions}
            onChange={(v) => updateRule(index, { field: v, operator: 'equals', value: '' })}
            placeholder="field"
          />
          {rule.field && (
            <>
              <SentenceToken
                mode="select"
                value={rule.operator}
                options={getOperatorOptions(rule.field)}
                onChange={(v) => updateRule(index, { operator: v })}
                placeholder="operator"
              />
              {!currentOpHidesValue(rule.operator) && (() => {
                const opts = getValueOptions(rule.field);
                if (opts?.type === 'enum') {
                  return (
                    <SentenceToken
                      mode="select"
                      value={rule.value}
                      options={opts.options}
                      onChange={(v) => updateRule(index, { value: v })}
                      placeholder="value"
                    />
                  );
                }
                if (opts?.type === 'dynamic') {
                  return (
                    <SentenceToken
                      mode="dynamic-select"
                      value={rule.value}
                      optionsUrl={opts.url}
                      onChange={(v) => updateRule(index, { value: v })}
                      placeholder="value"
                    />
                  );
                }
                return (
                  <SentenceToken
                    mode="text"
                    value={rule.value}
                    onChange={(v) => updateRule(index, { value: v })}
                    placeholder="value"
                  />
                );
              })()}
            </>
          )}
          <button
            type="button"
            className="inline-flex h-5 w-5 items-center justify-center rounded text-muted-foreground hover:text-destructive transition-colors"
            onClick={() => removeRule(index)}
          >
            <X className="h-3 w-3" />
          </button>
        </div>
      ))}

      {rules.length === 0 && (
        <div className="flex items-center gap-1.5">
          <span className="text-sm font-medium text-muted-foreground">Only if</span>
          <SentenceToken
            mode="select"
            value=""
            options={fieldOptions}
            onChange={(v) => updateRules([{ field: v, operator: 'equals', value: '' }])}
            placeholder="choose a field"
          />
        </div>
      )}

      <button
        type="button"
        className="text-xs text-muted-foreground hover:text-foreground transition-colors"
        onClick={addRule}
      >
        + Add rule
      </button>
    </div>
  );
}
