import { memo, useMemo, useState, useRef, useCallback } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatLabel } from '@/lib/constants';
import { useChartSize } from '@/lib/charts/use-chart-size';
import { niceLinearTicks, linearScale, bandScale } from '@/lib/charts/scales';
import { ChartTooltip, type TooltipRow } from './chart-tooltip';
import type { ReportsResponse } from '@/lib/api';

interface ChannelUsageProps {
  data: ReportsResponse;
}

const MARGIN = { top: 8, right: 12, left: 40, bottom: 24 };
const BAR_ANIMATION = 'bar-grow 400ms ease-out both';
const COLORS = [
  'var(--chart-1)', 'var(--chart-3)', 'var(--chart-2)',
  'var(--chart-4)', 'var(--chart-5)',
];

interface BarItem {
  name: string;
  count: number;
}

interface Hover {
  x: number;
  y: number;
  item: BarItem;
  index: number;
}

const MethodChart = memo(function MethodChart({ title, description, items }: { title: string; description: string; items: BarItem[] }) {
  const { ref, width, height } = useChartSize<HTMLDivElement>();
  const containerRef = useRef<HTMLDivElement>(null);
  const [hover, setHover] = useState<Hover | null>(null);

  const handleMouseMove = useCallback((item: BarItem, index: number, e: React.MouseEvent<SVGRectElement>) => {
    const svg = (e.target as SVGElement).closest('svg');
    if (!svg) return;
    const pt = svg.getBoundingClientRect();
    setHover({ x: e.clientX - pt.left, y: e.clientY - pt.top, item, index });
  }, []);

  if (items.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">No data for this period</p>
        </CardContent>
      </Card>
    );
  }

  const plotW = Math.max(0, width - MARGIN.left - MARGIN.right);
  const plotH = Math.max(0, height - MARGIN.top - MARGIN.bottom);

  const { ticks, y, band } = useMemo(() => {
    const maxVal = Math.max(...items.map((d) => d.count), 1);
    const _ticks = niceLinearTicks(0, maxVal, 5, true);
    const yMax = _ticks[_ticks.length - 1];
    return {
      ticks: _ticks,
      y: linearScale([0, yMax], [plotH, 0]),
      band: bandScale(items.map((d) => d.name), [0, plotW], 0.3),
    };
  }, [items, plotW, plotH]);

  const tooltipRows: TooltipRow[] | undefined = hover
    ? [{ color: COLORS[hover.index % COLORS.length], label: 'Count', value: hover.item.count }]
    : undefined;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent>
        <div ref={containerRef} className="relative h-56">
          <div ref={ref} className="h-full w-full">
            {width > 0 && (
              <svg width={width} height={height} className="overflow-visible">
                <g transform={`translate(${MARGIN.left},${MARGIN.top})`}>
                  {/* Grid lines */}
                  {ticks.map((t) => (
                    <line key={t} x1={0} x2={plotW} y1={y(t)} y2={y(t)} className="stroke-border/50" strokeDasharray="3 3" />
                  ))}

                  {/* Y axis labels */}
                  {ticks.map((t) => (
                    <text key={t} x={-8} y={y(t)} textAnchor="end" dominantBaseline="middle" className="fill-muted-foreground text-[11px]">
                      {t}
                    </text>
                  ))}

                  {/* Bars */}
                  {items.map((d, i) => {
                    const barX = band.get(d.name);
                    const barH = plotH - y(d.count);
                    return (
                      <rect
                        key={d.name}
                        x={barX}
                        y={y(d.count)}
                        width={band.bandwidth}
                        height={Math.max(0, barH)}
                        rx={4}
                        fill={COLORS[i % COLORS.length]}
                        style={{ transformOrigin: `${barX + band.bandwidth / 2}px ${plotH}px`, animation: BAR_ANIMATION, animationDelay: `${i * 60}ms` }}
                        className="transition-opacity hover:opacity-80"
                        onMouseMove={(e) => handleMouseMove(d, i, e)}
                        onMouseLeave={() => setHover(null)}
                      />
                    );
                  })}

                  {/* X axis labels */}
                  {items.map((d) => (
                    <text
                      key={d.name}
                      x={band.get(d.name) + band.bandwidth / 2}
                      y={plotH + 16}
                      textAnchor="middle"
                      className="fill-muted-foreground text-[11px]"
                    >
                      {d.name}
                    </text>
                  ))}
                </g>
              </svg>
            )}
          </div>
          {hover && tooltipRows && (
            <ChartTooltip x={hover.x + MARGIN.left} y={hover.y + MARGIN.top} title={hover.item.name} rows={tooltipRows} containerRef={containerRef} />
          )}
        </div>
      </CardContent>
    </Card>
  );
});

export function ChannelUsage({ data }: ChannelUsageProps) {
  const loginItems = useMemo(
    () => data.channel_usage.login_methods.map((m) => ({ name: formatLabel(m.method), count: m.count })),
    [data.channel_usage.login_methods],
  );

  const mfaItems = useMemo(
    () => data.channel_usage.mfa_methods.map((m) => ({ name: formatLabel(m.method), count: m.count })),
    [data.channel_usage.mfa_methods],
  );

  const socialItems = useMemo(
    () => data.channel_usage.social_providers.map((p) => ({ name: formatLabel(p.provider), count: p.count })),
    [data.channel_usage.social_providers],
  );

  const hasSocial = socialItems.length > 0;

  return (
    <div className={`grid gap-4 ${hasSocial ? 'md:grid-cols-3' : 'md:grid-cols-2'}`}>
      <MethodChart title="Login Methods" description="How users authenticate" items={loginItems} />
      <MethodChart title="MFA Methods" description="Active MFA enrollments by type" items={mfaItems} />
      {hasSocial && (
        <MethodChart title="Social Providers" description="Social login usage by provider" items={socialItems} />
      )}
    </div>
  );
}
