import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface SegmentedGroupOption<T extends string | number> {
  value: T;
  label: string;
  icon?: ReactNode;
}

interface SegmentedGroupProps<T extends string | number> {
  value: T;
  onChange: (value: T) => void;
  options: SegmentedGroupOption<T>[];
  /** "icon" = square icon-only buttons (default), "labeled" = wider buttons with icon + label */
  size?: 'icon' | 'labeled';
}

export function SegmentedGroup<T extends string | number>({
  value,
  onChange,
  options,
  size = 'icon',
}: SegmentedGroupProps<T>) {
  const isLabeled = size === 'labeled';

  return (
    <div className="flex rounded-lg border border-input" style={{ width: 'fit-content' }}>
      {options.map((opt, i) => {
        const active = value === opt.value;

        return (
          <button
            key={String(opt.value)}
            type="button"
            title={opt.label}
            onClick={() => onChange(opt.value)}
            className={cn(
              'flex items-center justify-center transition-colors',
              isLabeled ? 'h-9 gap-1.5 px-3 text-xs font-medium' : 'h-9 w-9',
              active
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:text-foreground hover:bg-muted',
              i === 0 && 'rounded-l-md',
              i === options.length - 1 && 'rounded-r-md',
            )}
          >
            {isLabeled ? (
              <>
                {opt.icon}
                {opt.label}
              </>
            ) : (
              opt.icon ?? opt.label
            )}
          </button>
        );
      })}
    </div>
  );
}
