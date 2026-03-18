import { useEffect } from 'react';
import type { SegmentConditionGroup, Tag } from '@/lib/api';
import { useSegmentPreview } from '@/hooks/use-segment-preview';
import { ConditionGroupComponent } from './condition-group';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SEGMENT_TEMPLATES } from '@/lib/constants';
import { Loader2 } from 'lucide-react';

interface SegmentBuilderProps {
  conditions: SegmentConditionGroup;
  tags: Tag[];
  onChange: (conditions: SegmentConditionGroup) => void;
}

export function SegmentBuilder({ conditions, tags, onChange }: SegmentBuilderProps) {
  const { count, loading, evaluate } = useSegmentPreview();

  useEffect(() => {
    if (conditions.conditions?.length || conditions.groups?.length) {
      evaluate(conditions);
    }
  }, [conditions, evaluate]);

  const handleTemplate = (templateName: string) => {
    const template = SEGMENT_TEMPLATES.find((t) => t.name === templateName);
    if (template) {
      onChange(template.conditions as unknown as SegmentConditionGroup);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <Select onValueChange={handleTemplate}>
          <SelectTrigger className="h-8 w-56 text-xs">
            <SelectValue placeholder="Start from template..." />
          </SelectTrigger>
          <SelectContent>
            {SEGMENT_TEMPLATES.map((t) => (
              <SelectItem key={t.name} value={t.name}>{t.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>

        <div className="flex items-center gap-2">
          {loading ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
          ) : (
            <Badge variant="secondary" className="text-xs">
              {count} {count === 1 ? 'contact' : 'contacts'} match
            </Badge>
          )}
        </div>
      </div>

      <ConditionGroupComponent group={conditions} tags={tags} onChange={onChange} />
    </div>
  );
}
