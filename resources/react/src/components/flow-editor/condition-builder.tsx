import { useMemo } from 'react';
import type { JsonSchema } from '@/lib/api';
import {
  type ConditionRule,
  type FieldOption,
  flattenSchemaFields,
  getOperatorsForType,
  createEmptyRule,
  OPERATORS,
} from '@/lib/condition-utils';
import { groupBy } from '@/lib/utils';
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
}

export function ConditionBuilder({ rules, onChange, payloadSchema }: ConditionBuilderProps) {
  const fields = payloadSchema?.properties
    ? flattenSchemaFields(payloadSchema.properties)
    : [];

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

  return (
    <div className="space-y-3">
      {rules.length > 0 && (
        <p className="text-sm text-muted-foreground">
          Only continue if <strong>all</strong> of these are true:
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
              onValueChange={(v) => updateRule(index, { field: v, operator: 'equals', value: '' })}
            >
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Select field">
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
            {!hideValue && (
              <Input
                className="flex-1"
                value={rule.value}
                onChange={(e) => updateRule(index, { value: e.target.value })}
                placeholder="Value"
                type={fieldType === 'integer' || fieldType === 'number' ? 'number' : 'text'}
              />
            )}

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
        <Plus className="mr-1 h-3.5 w-3.5" />
        Add condition
      </Button>
    </div>
  );
}
