import type { JsonSchema, JsonSchemaProperty } from '@/lib/api';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Braces } from 'lucide-react';

interface TemplateVariablePickerProps {
  payloadSchema: JsonSchema;
  onInsert: (variable: string) => void;
}

interface FlatVariable {
  path: string;
  label: string;
  type: string;
  example?: string;
}

function flattenProperties(
  properties: Record<string, JsonSchemaProperty>,
  prefix = '',
): FlatVariable[] {
  const result: FlatVariable[] = [];

  for (const [key, prop] of Object.entries(properties)) {
    const fullPath = prefix ? `${prefix}.${key}` : key;
    if (prop.type === 'object' && prop.properties) {
      result.push(...flattenProperties(prop.properties, fullPath));
    } else {
      result.push({
        path: fullPath,
        label: prop.title ?? key,
        type: prop.type,
        example: prop.example != null ? String(prop.example) : undefined,
      });
    }
  }

  return result;
}

export function TemplateVariablePicker({ payloadSchema, onInsert }: TemplateVariablePickerProps) {
  if (!payloadSchema?.properties || Object.keys(payloadSchema.properties).length === 0) {
    return null;
  }

  const variables = flattenProperties(payloadSchema.properties);

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
      <PopoverContent align="end" className="w-72 p-2">
        <p className="mb-2 px-2 text-xs font-medium text-muted-foreground">Insert variable</p>
        <div className="max-h-48 overflow-y-auto space-y-0.5">
          {variables.map(({ path, label, type, example }) => (
            <button
              key={path}
              type="button"
              className="flex w-full flex-col rounded px-2 py-1.5 text-left text-sm hover:bg-accent transition-colors"
              onClick={() => onInsert(`{{${path}}}`)}
            >
              <div className="flex w-full items-center justify-between">
                <span className="font-mono text-xs">{`{{${path}}}`}</span>
                <span className="ml-2 text-xs text-muted-foreground truncate">{label}</span>
              </div>
              <span className="text-[10px] text-muted-foreground/70">
                {type}{example ? ` · ${example}` : ''}
              </span>
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}
