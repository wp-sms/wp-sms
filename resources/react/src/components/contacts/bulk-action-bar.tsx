import { useState } from 'react';
import type { Tag } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CONTACT_STATUSES } from '@/lib/constants';
import { Trash2, Tag as TagIcon, CircleDot, X } from 'lucide-react';

interface BulkActionBarProps {
  count: number;
  tags: Tag[];
  onBulkAction: (action: string, params?: Record<string, unknown>) => Promise<void>;
  onClear: () => void;
}

export function BulkActionBar({ count, tags, onBulkAction, onClear }: BulkActionBarProps) {
  const [confirming, setConfirming] = useState(false);
  const [acting, setActing] = useState(false);

  const handleAction = async (action: string, params?: Record<string, unknown>) => {
    setActing(true);
    try {
      await onBulkAction(action, params);
    } finally {
      setActing(false);
      setConfirming(false);
    }
  };

  return (
    <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 rounded-lg border bg-background px-4 py-2.5 shadow-lg">
      <span className="text-sm font-medium">{count} selected</span>

      <div className="h-4 w-px bg-border" />

      <Select onValueChange={(tagId) => void handleAction('tag', { tag_id: tagId })} disabled={acting}>
        <SelectTrigger className="h-8 w-auto gap-1.5 text-sm border-0 bg-transparent px-2">
          <TagIcon className="h-3.5 w-3.5" />
          <span>Add Tag</span>
        </SelectTrigger>
        <SelectContent position="popper" side="top">
          {tags.map((tag) => (
            <SelectItem key={tag.id} value={tag.id}>
              <span className="flex items-center gap-2">
                <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                {tag.name}
              </span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select onValueChange={(status) => void handleAction('status', { status })} disabled={acting}>
        <SelectTrigger className="h-8 w-auto gap-1.5 text-sm border-0 bg-transparent px-2">
          <CircleDot className="h-3.5 w-3.5" />
          <span>Status</span>
        </SelectTrigger>
        <SelectContent position="popper" side="top">
          {CONTACT_STATUSES.map((s) => (
            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
          ))}
        </SelectContent>
      </Select>

      {confirming ? (
        <Button variant="destructive" size="sm" className="h-8" onClick={() => void handleAction('delete')} disabled={acting}>
          Sure? Delete {count}
        </Button>
      ) : (
        <Button
          variant="ghost"
          size="sm"
          className="h-8 text-destructive hover:text-destructive"
          onClick={() => { setConfirming(true); setTimeout(() => setConfirming(false), 3000); }}
          disabled={acting}
        >
          <Trash2 className="mr-1 h-3.5 w-3.5" /> Delete
        </Button>
      )}

      <div className="h-4 w-px bg-border" />

      <Button variant="ghost" size="sm" className="h-8 px-2" onClick={onClear}>
        <X className="h-3.5 w-3.5" />
      </Button>
    </div>
  );
}
