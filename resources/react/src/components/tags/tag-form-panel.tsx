import { useState, useEffect } from 'react';
import type { Tag } from '@/lib/api';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerDescription, DrawerFooter } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel } from '@/components/ui/field';
import { TAG_COLORS } from '@/lib/constants';
import { generateSlug } from '@/lib/utils';
import { Check } from 'lucide-react';

interface TagFormPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tag?: Tag | null;
  onSave: (data: { name: string; slug: string; color: string }) => Promise<unknown>;
}

export function TagFormPanel({ open, onOpenChange, tag, onSave }: TagFormPanelProps) {
  const isEdit = !!tag;
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [slugTouched, setSlugTouched] = useState(false);
  const [color, setColor] = useState(TAG_COLORS[0]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (tag) {
      setName(tag.name);
      setSlug(tag.slug);
      setSlugTouched(true);
      setColor(tag.color);
    } else {
      setName('');
      setSlug('');
      setSlugTouched(false);
      setColor(TAG_COLORS[0]);
    }
  }, [tag, open]);

  const handleNameChange = (value: string) => {
    setName(value);
    if (!slugTouched) {
      setSlug(generateSlug(value));
    }
  };

  const handleSubmit = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      await onSave({ name: name.trim(), slug: slug || generateSlug(name), color });
      onOpenChange(false);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent className="sm:max-w-lg overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle>{isEdit ? 'Edit Tag' : 'New Tag'}</DrawerTitle>
          <DrawerDescription>{isEdit ? 'Update tag details.' : 'Create a new tag to organize contacts.'}</DrawerDescription>
        </DrawerHeader>

        <div className="space-y-4 px-4">
          <Field>
            <FieldLabel htmlFor="tag-name">Name *</FieldLabel>
            <Input id="tag-name" value={name} onChange={(e) => handleNameChange(e.target.value)} placeholder="e.g. VIP" />
          </Field>

          <Field>
            <FieldLabel htmlFor="tag-slug">Slug</FieldLabel>
            <Input
              id="tag-slug"
              value={slug}
              onChange={(e) => { setSlug(e.target.value); setSlugTouched(true); }}
              placeholder="vip"
            />
          </Field>

          <Field>
            <FieldLabel>Color</FieldLabel>
            <div className="flex items-center gap-2">
              {TAG_COLORS.map((c) => (
                <button
                  key={c}
                  type="button"
                  className="relative h-7 w-7 rounded-full transition-transform hover:scale-110"
                  style={{ backgroundColor: c }}
                  onClick={() => setColor(c)}
                >
                  {color === c && <Check className="absolute inset-0 m-auto h-4 w-4 text-white" />}
                </button>
              ))}
            </div>
          </Field>
        </div>

        <DrawerFooter>
          <Button onClick={handleSubmit} disabled={saving || !name.trim()}>
            {saving ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </DrawerFooter>
      </DrawerContent>
    </Drawer>
  );
}
