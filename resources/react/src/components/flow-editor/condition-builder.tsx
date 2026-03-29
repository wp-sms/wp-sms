import { __ } from '@wordpress/i18n';
import { useMemo, useCallback } from 'react';
import type { JsonSchema } from '@/lib/api';
import {
  type ConditionRule,
  type FieldOption,
  flattenSchemaFields,
  getOperatorsForType,
  getDefaultOperator,
  createEmptyRule,
  getConditionValueOptions,
  OPERATORS,
} from '@/lib/condition-utils';
import { groupBy } from '@/lib/utils';
import { buildMergedSchema } from '@/lib/flow-utils';
import { useTriggers } from '@/hooks/use-triggers';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectGroup,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Plus, X } from 'lucide-react';

interface ConditionBuilderProps {
  rules: ConditionRule[];
  onChange: (rules: ConditionRule[]) => void;
  payloadSchema?: JsonSchema;
  triggerType?: string;
}

export function ConditionBuilder({ rules, onChange, payloadSchema, triggerType }: ConditionBuilderProps) {
  const { triggers } = useTriggers();
  const filterSchema = useMemo(() => {
    const trigger = triggers.find((t) => t.id === triggerType);
    return trigger?.filter_schema;
  }, [triggers, triggerType]);

  const merged = useMemo(() => buildMergedSchema(payloadSchema), [payloadSchema]);
  const fields = useMemo(
    () => (merged.properties ? flattenSchemaFields(merged.properties) : []),
    [merged],
  );

  const grouped = groupBy(fields, (f) => f.group ?? 'Fields');
  const fieldMap = useMemo(() => new Map(fields.map((f) => [f.path, f])), [fields]);

  const updateRule = (index: number, patch: Partial<ConditionRule>) => {
    const next = rules.map((r, i) => (i === index ? { ...r, ...patch } : r));
    onChange(next);
  };

  const removeRule = (index: number) => {
    onChange(rules.filter((_, i) => i !== index));
  };

  const addRule = () => {
    onChange([...rules, createEmptyRule()]);
  };

  const getField = (fieldPath: string): FieldOption | undefined => fieldMap.get(fieldPath);

  const getValueEnumOptions = useCallback(
    (field: string) => {
      const opts = getConditionValueOptions(field, filterSchema, merged, triggerType);
      return opts?.type === 'enum' ? opts.options : null;
    },
    [filterSchema, merged, triggerType],
  );

  return (
    <div className="space-y-3">
      {rules.length > 0 && (
        <p className="text-sm text-muted-foreground">
          {__('Only continue if', 'wp-sms')} <strong>{__('all', 'wp-sms')}</strong> {__('of these are true:', 'wp-sms')}
        </p>
      )}

      {rules.map((rule, index) => {
        const field = getField(rule.field);
        const fieldType = field?.type ?? 'string';
        const operators = getOperatorsForType(fieldType);
        const currentOp = OPERATORS.find((o) => o.value === rule.operator);
        const hideValue = currentOp?.hideValue ?? false;

        return (
          <div key={index} className="flex items-center gap-2">
            {/* Field select */}
            <Select
              value={rule.field}
              onValueChange={(v) => updateRule(index, { field: v, operator: getDefaultOperator(fieldMap.get(v)?.type ?? 'string'), value: '' })}
            >
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder={__('Select field', 'wp-sms')}>
                  {rule.field ? (field?.label ?? rule.field) : undefined}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {Object.entries(grouped).map(([group, items]) => (
                  <SelectGroup key={group}>
                    <SelectLabel>{group}</SelectLabel>
                    {items.map((f) => (
                      <SelectItem key={f.path} value={f.path}>
                        {f.label}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                ))}
              </SelectContent>
            </Select>

            {/* Operator select */}
            <Select
              value={rule.operator}
              onValueChange={(v) => updateRule(index, { operator: v })}
            >
              <SelectTrigger className="w-[160px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {operators.map((op) => (
                  <SelectItem key={op.value} value={op.value}>
                    {op.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {/* Value input */}
            {!hideValue && (() => {
              const enumOpts = getValueEnumOptions(rule.field);
              if (enumOpts) {
                const selected = enumOpts.find((o) => o.value === rule.value);
                return (
                  <Select
                    value={rule.value || undefined}
                    onValueChange={(v) => updateRule(index, { value: v })}
                  >
                    <SelectTrigger className="flex-1">
                      <SelectValue placeholder={__('Select value', 'wp-sms')}>
                        {selected?.label}
                      </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                      {enumOpts.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                );
              }
              return (
                <Input
                  className="flex-1"
                  value={rule.value}
                  onChange={(e) => updateRule(index, { value: e.target.value })}
                  placeholder={__('Value', 'wp-sms')}
                  type={fieldType === 'integer' || fieldType === 'number' ? 'number' : 'text'}
                />
              );
            })()}

            {/* Remove button */}
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 w-9 shrink-0 p-0 text-muted-foreground hover:text-destructive"
              onClick={() => removeRule(index)}
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        );
      })}

      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={addRule}
        className="w-full"
      >
        <Plus className="me-1 h-3.5 w-3.5" />
        {__('Add condition', 'wp-sms')}
      </Button>
    </div>
  );
}
