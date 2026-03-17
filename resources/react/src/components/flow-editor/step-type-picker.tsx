import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Plus, Zap, GitBranch, Clock } from 'lucide-react';

export type StepType = 'action' | 'condition' | 'delay';

interface StepTypePickerProps {
  onSelect: (type: StepType) => void;
  disabled?: boolean;
}

const STEP_TYPES = [
  { type: 'action' as const, label: 'Action', description: 'Run an action (send message, HTTP request, etc.)', icon: Zap },
  { type: 'condition' as const, label: 'Condition', description: 'If/then/else branching', icon: GitBranch },
  { type: 'delay' as const, label: 'Delay', description: 'Wait before continuing', icon: Clock },
];

export function StepTypePicker({ onSelect, disabled }: StepTypePickerProps) {
  const [open, setOpen] = useState(false);

  if (!open) {
    return (
      <Button
        variant="outline"
        size="sm"
        onClick={() => setOpen(true)}
        className="w-full"
        disabled={disabled}
      >
        <Plus className="mr-1.5 h-3.5 w-3.5" />
        Add Step
      </Button>
    );
  }

  return (
    <div className="rounded-lg border border-border bg-card p-2 space-y-1">
      <p className="px-2 py-1 text-xs font-medium text-muted-foreground">Choose step type</p>
      {STEP_TYPES.map(({ type, label, description, icon: Icon }) => (
        <button
          key={type}
          type="button"
          className="flex w-full items-start gap-3 rounded-md px-2 py-2 text-left text-sm hover:bg-accent transition-colors"
          onClick={() => { onSelect(type); setOpen(false); }}
        >
          <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
          <div>
            <span className="font-medium">{label}</span>
            <p className="text-xs text-muted-foreground">{description}</p>
          </div>
        </button>
      ))}
      <Button
        variant="ghost"
        size="sm"
        onClick={() => setOpen(false)}
        className="w-full text-xs"
      >
        Cancel
      </Button>
    </div>
  );
}
