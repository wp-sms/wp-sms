import type { JsonSchema } from '@/lib/api';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Braces } from 'lucide-react';

interface TemplateVariablePickerProps {
  payloadSchema: JsonSchema;
  onInsert: (variable: string) => void;
}

function flattenProperties(
  properties: Record<string, { type: string; title?: string; properties?: Record<string, { type: string; title?: string }> }>,
  prefix = '',
): { path: string; label: string }[] {
  const result: { path: string; label: string }[] = [];

  for (const [key, prop] of Object.entries(properties)) {
    const fullPath = prefix ? `${prefix}.${key}` : key;
    if (prop.type === 'object' && prop.properties) {
      result.push(...flattenProperties(prop.properties as Record<string, { type: string; title?: string; properties?: Record<string, { type: string; title?: string }> }>, fullPath));
    } else {
      result.push({ path: fullPath, label: prop.title ?? key });
    }
  }

  return result;
}

export function TemplateVariablePicker({ payloadSchema, onInsert }: TemplateVariablePickerProps) {
  if (!payloadSchema?.properties || Object.keys(payloadSchema.properties).length === 0) {
    return null;
  }

  const variables = flattenProperties(payloadSchema.properties as Record<string, { type: string; title?: string; properties?: Record<string, { type: string; title?: string }> }>);

  if (variables.length === 0) return null;

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
          title="Insert template variable"
        >
          <Braces className="h-3.5 w-3.5" />
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-64 p-2">
        <p className="mb-2 px-2 text-xs font-medium text-muted-foreground">Insert variable</p>
        <div className="max-h-48 overflow-y-auto space-y-0.5">
          {variables.map(({ path, label }) => (
            <button
              key={path}
              type="button"
              className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent transition-colors"
              onClick={() => onInsert(`{{${path}}}`)}
            >
              <span className="font-mono text-xs">{`{{${path}}}`}</span>
              <span className="ml-2 text-xs text-muted-foreground truncate">{label}</span>
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}
