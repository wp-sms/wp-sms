import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { TAG_COLORS } from '@/lib/constants';
import { Check } from 'lucide-react';

interface TagFormProps {
  initial?: { name: string; slug?: string; color: string };
  onSave: (data: { name: string; slug: string; color: string }) => void | Promise<void>;
  onCancel: () => void;
}

function nameToSlug(name: string): string {
  return name.toLowerCase().trim().replace(/\s+/g, '-');
}

export function TagForm({ initial, onSave, onCancel }: TagFormProps) {
  const [name, setName] = useState(initial?.name ?? '');
  const [slug, setSlug] = useState(initial?.slug ?? '');
  const [slugTouched, setSlugTouched] = useState(false);
  const [color, setColor] = useState(initial?.color ?? TAG_COLORS[0]);
  const [saving, setSaving] = useState(false);

  const handleNameChange = (value: string) => {
    setName(value);
    if (!slugTouched) {
      setSlug(nameToSlug(value));
    }
  };

  const handleSubmit = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      await onSave({ name: name.trim(), slug: slug || nameToSlug(name), color });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex items-center gap-3">
      <Input
        placeholder="Tag name"
        value={name}
        onChange={(e) => handleNameChange(e.target.value)}
        className="h-8 text-sm flex-1"
        autoFocus
        onKeyDown={(e) => { if (e.key === 'Enter') void handleSubmit(); }}
      />
      <Input
        placeholder="Slug"
        value={slug}
        onChange={(e) => { setSlug(e.target.value); setSlugTouched(true); }}
        className="h-8 text-sm w-32"
        onKeyDown={(e) => { if (e.key === 'Enter') void handleSubmit(); }}
      />
      <div className="flex items-center gap-1">
        {TAG_COLORS.map((c) => (
          <button
            key={c}
            type="button"
            className="relative h-5 w-5 rounded-full transition-transform hover:scale-110"
            style={{ backgroundColor: c }}
            onClick={() => setColor(c)}
          >
            {color === c && <Check className="absolute inset-0 m-auto h-3 w-3 text-white" />}
          </button>
        ))}
      </div>
      <Button size="sm" className="h-8" onClick={handleSubmit} disabled={!name.trim() || saving}>
        {saving ? '...' : initial ? 'Update' : 'Create'}
      </Button>
      <Button variant="ghost" size="sm" className="h-8" onClick={onCancel}>Cancel</Button>
    </div>
  );
}
