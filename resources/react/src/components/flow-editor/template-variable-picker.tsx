import { useMemo } from 'react';
import type { JsonSchema } from '@/lib/api';
import { type FieldOption, flattenSchemaFields } from '@/lib/condition-utils';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Braces } from 'lucide-react';
import { groupBy } from '@/lib/utils';
import { buildMergedSchema } from '@/lib/flow-utils';

interface TemplateVariablePickerProps {
  payloadSchema?: JsonSchema;
  onInsert: (variable: string) => void;
}

export function TemplateVariablePicker({ payloadSchema, onInsert }: TemplateVariablePickerProps) {
  const merged = useMemo(() => buildMergedSchema(payloadSchema), [payloadSchema]);

  if (!merged.properties || Object.keys(merged.properties).length === 0) {
    return null;
  }

  const variables = flattenSchemaFields(merged.properties);

  if (variables.length === 0) return null;

  const grouped = groupBy(variables, (v) => v.group ?? 'Fields');
  const groupNames = Object.keys(grouped);
  const hasMultipleGroups = groupNames.length > 1 || (groupNames.length === 1 && groupNames[0] !== 'Fields');

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
          {hasMultipleGroups
            ? groupNames.map((groupName) => (
                <div key={groupName}>
                  <p className="px-2 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground/60">
                    {groupName}
                  </p>
                  {grouped[groupName].map((v) => (
                    <VariableButton key={v.path} variable={v} onInsert={onInsert} />
                  ))}
                </div>
              ))
            : variables.map((v) => (
                <VariableButton key={v.path} variable={v} onInsert={onInsert} />
              ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}

function VariableButton({
  variable: { path, label, type, example },
  onInsert,
}: {
  variable: FieldOption;
  onInsert: (variable: string) => void;
}) {
  return (
    <button
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
  );
}
