import type { SegmentCondition, Tag } from '@/lib/api';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ATTRIBUTE_FIELDS, SEGMENT_OPERATORS } from '@/lib/constants';
import { X } from 'lucide-react';

interface ConditionRowProps {
  condition: SegmentCondition;
  tags: Tag[];
  onChange: (condition: SegmentCondition) => void;
  onRemove: () => void;
}

const OPERATORS_BY_TYPE: Record<string, string[]> = {
  attribute: ['equals', 'not_equals', 'contains', 'starts_with', 'is_empty', 'is_not_empty'],
  tag: ['has', 'not_has'],
};

const NO_VALUE_OPS = ['is_empty', 'is_not_empty'];

export function ConditionRow({ condition, tags, onChange, onRemove }: ConditionRowProps) {
  const type = condition.type || 'attribute';
  const operators = OPERATORS_BY_TYPE[type] || OPERATORS_BY_TYPE.attribute;
  const showValue = !NO_VALUE_OPS.includes(condition.operator);

  return (
    <div className="flex items-center gap-2">
      <Select value={type} onValueChange={(v) => onChange({ ...condition, type: v as 'attribute' | 'tag', field: '', operator: v === 'tag' ? 'has' : 'equals', value: '' })}>
        <SelectTrigger className="h-8 w-24 text-xs">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="attribute">Field</SelectItem>
          <SelectItem value="tag">Tag</SelectItem>
        </SelectContent>
      </Select>

      {type === 'attribute' && (
        <Select value={condition.field || ''} onValueChange={(v) => onChange({ ...condition, field: v })}>
          <SelectTrigger className="h-8 w-28 text-xs">
            <SelectValue placeholder="Field" />
          </SelectTrigger>
          <SelectContent>
            {ATTRIBUTE_FIELDS.map((f) => (
              <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      <Select value={condition.operator} onValueChange={(v) => onChange({ ...condition, operator: v })}>
        <SelectTrigger className="h-8 w-28 text-xs">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {operators.map((op) => (
            <SelectItem key={op} value={op}>{SEGMENT_OPERATORS[op as keyof typeof SEGMENT_OPERATORS]}</SelectItem>
          ))}
        </SelectContent>
      </Select>

      {showValue && type === 'attribute' && (
        <Input
          className="h-8 text-xs flex-1"
          placeholder="Value"
          value={condition.value || ''}
          onChange={(e) => onChange({ ...condition, value: e.target.value })}
        />
      )}

      {type === 'tag' && (
        <Select value={condition.value || ''} onValueChange={(v) => onChange({ ...condition, value: v })}>
          <SelectTrigger className="h-8 flex-1 text-xs">
            <SelectValue placeholder="Select tag" />
          </SelectTrigger>
          <SelectContent>
            {tags.map((tag) => (
              <SelectItem key={tag.slug} value={tag.slug}>
                <span className="flex items-center gap-2">
                  <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                  {tag.name}
                </span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      <Button variant="ghost" size="sm" className="h-8 w-8 p-0 shrink-0" onClick={onRemove}>
        <X className="h-3.5 w-3.5" />
      </Button>
    </div>
  );
}
