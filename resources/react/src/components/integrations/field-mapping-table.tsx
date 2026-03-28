import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ATTRIBUTE_FIELDS } from '@/lib/constants';

interface FieldMappingTableProps {
  mapping: Record<string, string>;
  availableFields: Record<string, { label: string; type: string }>;
  onChange: (mapping: Record<string, string>) => void;
}

const MAPPABLE_FIELDS = ATTRIBUTE_FIELDS.filter(
  (f) => f.value !== 'status' && f.value !== 'source',
);

export function FieldMappingTable({ mapping, availableFields, onChange }: FieldMappingTableProps) {
  const sourceOptions = Object.entries(availableFields);

  const handleChange = (contactField: string, sourceField: string) => {
    onChange({ ...mapping, [contactField]: sourceField === '__none__' ? '' : sourceField });
  };

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-3 text-xs font-medium text-muted-foreground uppercase tracking-wider">
        <span>Contact Field</span>
        <span>Source Field</span>
      </div>
      {MAPPABLE_FIELDS.map(({ value, label }) => (
        <div key={value} className="grid grid-cols-2 gap-3 items-center">
          <span className="text-sm font-medium">{label}</span>
          <Select
            value={mapping[value] || '__none__'}
            onValueChange={(v) => handleChange(value, v)}
          >
            <SelectTrigger className="h-8 text-sm">
              <SelectValue placeholder="Not mapped" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Not mapped</SelectItem>
              {sourceOptions.map(([fieldKey, field]) => (
                <SelectItem key={fieldKey} value={fieldKey}>
                  {field.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      ))}
    </div>
  );
}
