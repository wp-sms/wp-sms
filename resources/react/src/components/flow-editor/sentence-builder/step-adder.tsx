import { __ } from '@wordpress/i18n';
import type { StepType } from '@/lib/flow-utils';
import { STEP_ICONS } from './step-card';

interface StepAdderProps {
  onAdd: (type: StepType) => void;
}

export function StepAdder({ onAdd }: StepAdderProps) {
  return (
    <div className="flex items-center gap-3 pt-1">
      <button
        type="button"
        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
        onClick={() => onAdd('action')}
      >
        <STEP_ICONS.action className="h-3 w-3" />
        {__('+ Then also...', 'wp-sms')}
      </button>
      <button
        type="button"
        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
        onClick={() => onAdd('condition')}
      >
        <STEP_ICONS.condition className="h-3 w-3" />
        {__('+ Only if...', 'wp-sms')}
      </button>
      <button
        type="button"
        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
        onClick={() => onAdd('delay')}
      >
        <STEP_ICONS.delay className="h-3 w-3" />
        {__('+ Wait...', 'wp-sms')}
      </button>
    </div>
  );
}
