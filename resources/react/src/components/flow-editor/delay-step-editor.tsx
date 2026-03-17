import { useMemo } from 'react';
import type { DelayNode, JsonSchema } from '@/lib/api';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { FlowStepList } from '@/components/flow-step-list';

interface DelayStepEditorProps {
  step: DelayNode;
  onChange: (step: DelayNode) => void;
  payloadSchema?: JsonSchema;
  depth: number;
}

type TimeUnit = 'seconds' | 'minutes' | 'hours' | 'days';

const UNIT_SECONDS: Record<TimeUnit, number> = {
  seconds: 1,
  minutes: 60,
  hours: 3600,
  days: 86400,
};

function detectUnit(seconds: number): TimeUnit {
  if (seconds % 86400 === 0 && seconds >= 86400) return 'days';
  if (seconds % 3600 === 0 && seconds >= 3600) return 'hours';
  if (seconds % 60 === 0 && seconds >= 60) return 'minutes';
  return 'seconds';
}

export function DelayStepEditor({ step, onChange, payloadSchema, depth }: DelayStepEditorProps) {
  const unit = useMemo(() => detectUnit(step.duration), [step.duration]);
  const displayValue = Math.round(step.duration / UNIT_SECONDS[unit]);

  const handleValueChange = (val: string, u: TimeUnit = unit) => {
    const num = Math.max(0, parseInt(val) || 0);
    onChange({ ...step, duration: num * UNIT_SECONDS[u] });
  };

  const handleUnitChange = (u: string) => {
    const newUnit = u as TimeUnit;
    onChange({ ...step, duration: displayValue * UNIT_SECONDS[newUnit] });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-2">
        <Field className="flex-1">
          <FieldLabel htmlFor={`delay-val-${step.id}`}>Wait for</FieldLabel>
          <Input
            id={`delay-val-${step.id}`}
            type="number"
            min={0}
            value={displayValue}
            onChange={(e) => handleValueChange(e.target.value)}
          />
        </Field>
        <Field className="w-32">
          <Select value={unit} onValueChange={handleUnitChange}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="seconds">Seconds</SelectItem>
              <SelectItem value="minutes">Minutes</SelectItem>
              <SelectItem value="hours">Hours</SelectItem>
              <SelectItem value="days">Days</SelectItem>
            </SelectContent>
          </Select>
        </Field>
      </div>

      {/* Continuation steps */}
      <div className="space-y-2">
        <p className="text-sm font-medium text-muted-foreground">Then do</p>
        <div className="border-l-2 border-blue-200 pl-4">
          <FlowStepList
            steps={step.then}
            onChange={(then) => onChange({ ...step, then })}
            payloadSchema={payloadSchema}
            depth={depth + 1}
          />
        </div>
      </div>
    </div>
  );
}
