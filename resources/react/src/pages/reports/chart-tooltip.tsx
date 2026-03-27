import { type ReactNode } from 'react';

export interface TooltipRow {
  color: string;
  label: string;
  value: number;
}

interface ChartTooltipProps {
  x: number;
  y: number;
  title?: ReactNode;
  rows: TooltipRow[];
  containerRef: React.RefObject<HTMLElement | null>;
}

export function ChartTooltip({ x, y, title, rows, containerRef }: ChartTooltipProps) {
  const container = containerRef.current;
  if (!container) return null;

  const rect = container.getBoundingClientRect();
  // Flip tooltip to left side if it would overflow
  const flipX = x > rect.width * 0.7;

  return (
    <div
      className="pointer-events-none absolute z-10 rounded-lg border border-border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
      style={{
        left: flipX ? undefined : x + 12,
        right: flipX ? rect.width - x + 12 : undefined,
        top: Math.max(0, y - 40),
      }}
    >
      {title && <div className="mb-1 font-medium">{title}</div>}
      {rows.map((row) => (
        <div key={row.label} className="flex items-center gap-2">
          <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: row.color }} />
          <span className="text-muted-foreground">{row.label}</span>
          <span className="ml-auto font-medium tabular-nums">{row.value.toLocaleString()}</span>
        </div>
      ))}
    </div>
  );
}
