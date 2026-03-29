import { __ } from '@wordpress/i18n';
import type { SegmentConditionGroup, SegmentCondition, Tag } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ConditionRow } from './condition-row';
import { Plus } from 'lucide-react';

interface ConditionGroupProps {
  group: SegmentConditionGroup;
  tags: Tag[];
  onChange: (group: SegmentConditionGroup) => void;
  depth?: number;
}

const MAX_DEPTH = 2;

const emptyCondition = (): SegmentCondition => ({
  type: 'attribute',
  field: 'status',
  operator: 'equals',
  value: '',
});

export function ConditionGroupComponent({ group, tags, onChange, depth = 0 }: ConditionGroupProps) {
  const conditions = group.conditions ?? [];
  const groups = group.groups ?? [];

  const updateCondition = (index: number, condition: SegmentCondition) => {
    const updated = [...conditions];
    updated[index] = condition;
    onChange({ ...group, conditions: updated });
  };

  const removeCondition = (index: number) => {
    onChange({ ...group, conditions: conditions.filter((_, i) => i !== index) });
  };

  const addCondition = () => {
    onChange({ ...group, conditions: [...conditions, emptyCondition()] });
  };

  const updateSubGroup = (index: number, subGroup: SegmentConditionGroup) => {
    const updated = [...groups];
    updated[index] = subGroup;
    onChange({ ...group, groups: updated });
  };

  const addGroup = () => {
    onChange({ ...group, groups: [...groups, { match: 'all', conditions: [emptyCondition()] }] });
  };

  const removeGroup = (index: number) => {
    onChange({ ...group, groups: groups.filter((_, i) => i !== index) });
  };

  return (
    <div className={`space-y-2 ${depth > 0 ? 'ms-4 ps-3 border-s-2 border-border/50' : ''}`}>
      <div className="flex items-center gap-2">
        <span className="text-xs text-muted-foreground">{__('Match', 'wp-sms')}</span>
        <Select value={group.match} onValueChange={(v) => onChange({ ...group, match: v as 'all' | 'any' })}>
          <SelectTrigger className="h-7 w-16 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{__('All', 'wp-sms')}</SelectItem>
            <SelectItem value="any">{__('Any', 'wp-sms')}</SelectItem>
          </SelectContent>
        </Select>
        <span className="text-xs text-muted-foreground">{__('of these conditions', 'wp-sms')}</span>
      </div>

      {conditions.map((condition, i) => (
        <ConditionRow
          key={i}
          condition={condition}
          tags={tags}
          onChange={(c) => updateCondition(i, c)}
          onRemove={() => removeCondition(i)}
        />
      ))}

      {groups.map((subGroup, i) => (
        <div key={i} className="relative">
          <Button
            variant="ghost"
            size="sm"
            className="absolute -end-1 -top-1 h-5 w-5 p-0 text-muted-foreground"
            onClick={() => removeGroup(i)}
          >
            {'x'}
          </Button>
          <ConditionGroupComponent
            group={subGroup}
            tags={tags}
            onChange={(g) => updateSubGroup(i, g)}
            depth={depth + 1}
          />
        </div>
      ))}

      <div className="flex items-center gap-2">
        <Button variant="outline" size="sm" className="h-7 text-xs" onClick={addCondition}>
          <Plus className="me-1 h-3 w-3" /> {__('Condition', 'wp-sms')}
        </Button>
        {depth < MAX_DEPTH && (
          <Button variant="outline" size="sm" className="h-7 text-xs" onClick={addGroup}>
            <Plus className="me-1 h-3 w-3" /> {__('Group', 'wp-sms')}
          </Button>
        )}
      </div>
    </div>
  );
}
