import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';
import type { DelayNode } from '@/lib/api';
import { SentenceToken } from './sentence-token';

interface DelaySentenceProps {
  step: DelayNode;
  onChange: (step: DelayNode) => void;
}

type TimeUnit = 'seconds' | 'minutes' | 'hours' | 'days';

const UNIT_SECONDS: Record<TimeUnit, number> = {
  seconds: 1,
  minutes: 60,
  hours: 3600,
  days: 86400,
};

const UNIT_OPTIONS = [
  { value: 'seconds', label: 'seconds' },
  { value: 'minutes', label: 'minutes' },
  { value: 'hours', label: 'hours' },
  { value: 'days', label: 'days' },
];

function detectUnit(seconds: number): TimeUnit {
  if (seconds % 86400 === 0 && seconds >= 86400) return 'days';
  if (seconds % 3600 === 0 && seconds >= 3600) return 'hours';
  if (seconds % 60 === 0 && seconds >= 60) return 'minutes';
  return 'seconds';
}

export function DelaySentence({ step, onChange }: DelaySentenceProps) {
  const unit = useMemo(() => detectUnit(step.duration), [step.duration]);
  const displayValue = Math.round(step.duration / UNIT_SECONDS[unit]);

  const handleValueChange = (val: number) => {
    onChange({ ...step, duration: val * UNIT_SECONDS[unit] });
  };

  const handleUnitChange = (u: string) => {
    const newUnit = u as TimeUnit;
    onChange({ ...step, duration: displayValue * UNIT_SECONDS[newUnit] });
  };

  return (
    <div className="flex items-center gap-1.5 flex-wrap">
      <span className="text-sm font-medium text-muted-foreground">{__('Wait', 'wp-sms')}</span>
      <SentenceToken
        mode="number"
        value={String(displayValue)}
        onChange={handleValueChange}
        placeholder="0"
        min={1}
      />
      <SentenceToken
        mode="select"
        value={unit}
        options={UNIT_OPTIONS}
        onChange={handleUnitChange}
        placeholder="unit"
      />
    </div>
  );
}
