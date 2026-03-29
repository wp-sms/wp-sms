import { useCallback, type ReactNode } from 'react';
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
  /** Accessible label for the group (announced by screen readers) */
  'aria-label'?: string;
}

export function SegmentedGroup<T extends string | number>({
  value,
  onChange,
  options,
  size = 'icon',
  'aria-label': ariaLabel,
}: SegmentedGroupProps<T>) {
  const isLabeled = size === 'labeled';
  // Inline !important overrides Field's [&>*]:w-full (both are !important via Tailwind v4, parent wins by specificity)
  const fitRef = useCallback((el: HTMLDivElement | null) => {
    el?.style.setProperty('width', 'fit-content', 'important');
  }, []);

  return (
    <div ref={fitRef} role="group" aria-label={ariaLabel} className="flex rounded-lg border-2 border-input">
      {options.map((opt, i) => {
        const active = value === opt.value;

        return (
          <button
            key={String(opt.value)}
            type="button"
            title={opt.label}
            aria-label={opt.label}
            aria-pressed={active}
            onClick={() => onChange(opt.value)}
            className={cn(
              'flex items-center justify-center transition-colors',
              isLabeled ? 'h-9 gap-1.5 px-3 text-xs font-medium' : 'h-9 w-9',
              active
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:text-foreground hover:bg-muted',
              i === 0 && 'rounded-s-md',
              i === options.length - 1 && 'rounded-e-md',
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
