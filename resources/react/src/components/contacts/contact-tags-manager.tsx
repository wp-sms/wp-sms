import { useState } from 'react';
import type { Tag } from '@/lib/api';
import { TagChips } from './tag-chips';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus } from 'lucide-react';

interface ContactTagsManagerProps {
  contactId: string;
  tags: Tag[];
  allTags: Tag[];
  onAdd: (contactId: string, tagId: string) => Promise<void>;
  onRemove: (contactId: string, tagId: string) => Promise<void>;
}

export function ContactTagsManager({ contactId, tags, allTags, onAdd, onRemove }: ContactTagsManagerProps) {
  const [adding, setAdding] = useState(false);
  const assignedIds = new Set(tags.map((t) => t.id));
  const available = allTags.filter((t) => !assignedIds.has(t.id));

  const handleAdd = async (tagId: string) => {
    await onAdd(contactId, tagId);
    setAdding(false);
  };

  return (
    <div className="space-y-2">
      <TagChips tags={tags} onRemove={(tagId) => void onRemove(contactId, tagId)} />
      {adding ? (
        <Select onValueChange={(v) => void handleAdd(v)}>
          <SelectTrigger className="h-8 text-sm w-48">
            <SelectValue placeholder="Select tag..." />
          </SelectTrigger>
          <SelectContent>
            {available.map((tag) => (
              <SelectItem key={tag.id} value={tag.id}>
                <span className="flex items-center gap-2">
                  <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                  {tag.name}
                </span>
              </SelectItem>
            ))}
            {!available.length && (
              <div className="px-2 py-1.5 text-xs text-muted-foreground">All tags assigned</div>
            )}
          </SelectContent>
        </Select>
      ) : (
        <Button variant="outline" size="sm" className="h-7" onClick={() => setAdding(true)}>
          <Plus className="mr-1 h-3 w-3" /> Add tag
        </Button>
      )}
    </div>
  );
}
