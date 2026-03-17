import { useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover';
import { Plus, Zap, GitBranch, Clock } from 'lucide-react';
import { STEP_COLORS } from '@/lib/flow-utils';
import { cn } from '@/lib/utils';

export type StepType = 'action' | 'condition' | 'delay';

interface StepTypePickerProps {
  onSelect: (type: StepType) => void;
  disabled?: boolean;
}

export const STEP_TYPES = [
  { type: 'action' as const, label: 'Action', description: 'Run an action (send message, HTTP request, etc.)', icon: Zap },
  { type: 'condition' as const, label: 'Condition', description: 'If/then/else branching', icon: GitBranch },
  { type: 'delay' as const, label: 'Delay', description: 'Wait before continuing', icon: Clock },
];

function StepTypeOption({
  type,
  icon: Icon,
  label,
  description,
  size = 'md',
  onClick,
}: {
  type: StepType;
  icon: LucideIcon;
  label: string;
  description: string;
  size?: 'sm' | 'md';
  onClick: () => void;
}) {
  const colors = STEP_COLORS[type];
  const isSmall = size === 'sm';
  return (
    <button
      type="button"
      className={cn(
        'flex w-full items-start rounded-md text-left text-sm hover:bg-accent transition-colors',
        isSmall ? 'gap-2.5 px-2 py-1.5' : 'gap-3 px-2 py-2',
      )}
      onClick={onClick}
    >
      <div className={cn(
        'mt-0.5 flex items-center justify-center rounded-full shrink-0',
        isSmall ? 'h-5 w-5' : 'h-6 w-6',
        colors.iconBg,
      )}>
        <Icon className={cn(isSmall ? 'h-3 w-3' : 'h-3.5 w-3.5', colors.iconFg)} />
      </div>
      <div className={cn(isSmall && 'min-w-0')}>
        <span className="font-medium">{label}</span>
        <p className={cn('text-xs text-muted-foreground', isSmall && 'truncate')}>{description}</p>
      </div>
    </button>
  );
}

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
      {STEP_TYPES.map((s) => (
        <StepTypeOption key={s.type} {...s} onClick={() => { onSelect(s.type); setOpen(false); }} />
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

interface StepTypePopoverProps {
  onSelect: (type: StepType) => void;
}

export function StepTypePopover({ onSelect }: StepTypePopoverProps) {
  const [open, setOpen] = useState(false);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          className="flex h-5 w-5 items-center justify-center rounded-full border border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
        >
          <Plus className="h-3 w-3" />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-56 p-1.5" align="center">
        <p className="px-2 py-1 text-xs font-medium text-muted-foreground">Insert step</p>
        {STEP_TYPES.map((s) => (
          <StepTypeOption key={s.type} {...s} size="sm" onClick={() => { onSelect(s.type); setOpen(false); }} />
        ))}
      </PopoverContent>
    </Popover>
  );
}
