import type { Tag } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { X } from 'lucide-react';

interface TagChipsProps {
  tags: Tag[];
  onRemove?: (tagId: string) => void;
  size?: 'sm' | 'default';
}

export function TagChips({ tags, onRemove, size = 'default' }: TagChipsProps) {
  if (!tags.length) return null;

  return (
    <div className="flex flex-wrap gap-1">
      {tags.map((tag) => (
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
    </div>
  );
}
