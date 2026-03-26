import type { Tag } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { X } from 'lucide-react';

interface TagChipsProps {
  tags: Tag[];
  onRemove?: (tagId: string) => void;
  size?: 'sm' | 'default';
  maxItems?: number;
}

export function TagChips({ tags, onRemove, size = 'default', maxItems }: TagChipsProps) {
  if (!tags.length) return null;

  const visible = maxItems != null ? tags.slice(0, maxItems) : tags;
  const overflow = maxItems != null ? tags.length - maxItems : 0;

  return (
    <div className="flex flex-wrap items-center gap-1">
      {visible.map((tag) => (
        <Badge
          key={tag.id}
          variant="outline"
          className={`gap-1 ${size === 'sm' ? 'text-[10px] px-1.5 py-0' : 'text-xs px-2 py-0.5'}`}
          style={{ borderColor: tag.color + '40', backgroundColor: tag.color + '10', color: tag.color }}
        >
          <span className="inline-block h-1.5 w-1.5 rounded-full" style={{ backgroundColor: tag.color }} />
          {tag.name}
          {onRemove && (
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); onRemove(tag.id); }}
              className="ml-0.5 hover:opacity-70"
            >
              <X className="h-2.5 w-2.5" />
            </button>
          )}
        </Badge>
      ))}
      {overflow > 0 && (
        <span className="text-xs text-muted-foreground">+{overflow}</span>
      )}
    </div>
  );
}
