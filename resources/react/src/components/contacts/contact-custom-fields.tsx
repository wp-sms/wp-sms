import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Plus, Trash2 } from 'lucide-react';

interface ContactCustomFieldsProps {
  fields: Record<string, unknown>;
  onChange?: (fields: Record<string, unknown>) => void;
  readOnly?: boolean;
}

export function ContactCustomFields({ fields, onChange, readOnly = false }: ContactCustomFieldsProps) {
  const entries = Object.entries(fields);

  const handleAdd = () => {
    onChange?.({ ...fields, '': '' });
  };

  const handleKeyChange = (oldKey: string, newKey: string) => {
    const updated = { ...fields };
    delete updated[oldKey];
    updated[newKey] = fields[oldKey] ?? '';
    onChange?.(updated);
  };

  const handleValueChange = (key: string, value: string) => {
    onChange?.({ ...fields, [key]: value });
  };

  const handleRemove = (key: string) => {
    const updated = { ...fields };
    delete updated[key];
    onChange?.(updated);
  };

  if (readOnly) {
    if (!entries.length) return <p className="text-xs text-muted-foreground">No custom fields</p>;
    return (
      <div className="space-y-1.5">
        {entries.map(([key, value]) => (
          <div key={key} className="flex items-center gap-2 text-sm">
            <span className="font-medium text-muted-foreground min-w-[80px]">{key}:</span>
            <span>{String(value)}</span>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {entries.map(([key, value], i) => (
        <div key={i} className="flex items-center gap-2">
          <Input
            placeholder="Key"
            value={key}
            onChange={(e) => handleKeyChange(key, e.target.value)}
            className="h-8 text-sm flex-1"
          />
          <Input
            placeholder="Value"
            value={String(value ?? '')}
            onChange={(e) => handleValueChange(key, e.target.value)}
            className="h-8 text-sm flex-1"
          />
          <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => handleRemove(key)}>
            <Trash2 className="h-3.5 w-3.5 text-destructive" />
          </Button>
        </div>
      ))}
      <Button variant="outline" size="sm" className="h-7" onClick={handleAdd}>
        <Plus className="mr-1 h-3 w-3" /> Add field
      </Button>
    </div>
  );
}
