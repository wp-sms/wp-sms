import { __ } from '@wordpress/i18n';
import { useMemo, useState, useRef, useCallback } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useChartSize } from '@/lib/charts/use-chart-size';
import { niceLinearTicks, linearScale, bandScale } from '@/lib/charts/scales';
import { ChartTooltip, type TooltipRow } from './chart-tooltip';
import type { ReportsResponse } from '@/lib/api';

interface ActivityChartProps {
  data: ReportsResponse;
}

const SERIES = [
  { key: 'logins' as const, label: __('Logins', 'wp-sms'), color: 'var(--chart-1)' },
  { key: 'failures' as const, label: __('Failures', 'wp-sms'), color: 'var(--chart-2)' },
  { key: 'registrations' as const, label: __('Registrations', 'wp-sms'), color: 'var(--chart-3)' },
];

type TimelineEntry = ReportsResponse['auth_activity']['timeline'][number];

const MARGIN = { top: 40, right: 12, left: 40, bottom: 24 };
const STACKED_THRESHOLD = 20;
const BAR_ANIMATION = 'bar-grow 400ms ease-out both';

function formatTick(v: string): string {
  return new Date(v + 'T00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function formatTooltipLabel(v: string): string {
  return new Date(v + 'T00:00').toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
}

interface Hover {
  x: number;
  y: number;
  entry: TimelineEntry;
}

export function ActivityChart({ data }: ActivityChartProps) {
  const timeline = data.auth_activity.timeline;
  const { ref, width, height } = useChartSize<HTMLDivElement>();
  const containerRef = useRef<HTMLDivElement>(null);
  const [hover, setHover] = useState<Hover | null>(null);

  const handleMouseMove = useCallback((entry: TimelineEntry, e: React.MouseEvent<SVGRectElement>) => {
    const svg = (e.target as SVGElement).closest('svg');
    if (!svg) return;
    const pt = svg.getBoundingClientRect();
    setHover({ x: e.clientX - pt.left, y: e.clientY - pt.top, entry });
  }, []);

  if (timeline.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{__('Auth Activity', 'wp-sms')}</CardTitle>
          <CardDescription>{__('No activity data for this period', 'wp-sms')}</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const stacked = timeline.length >= STACKED_THRESHOLD;
  const plotW = Math.max(0, width - MARGIN.left - MARGIN.right);
  const plotH = Math.max(0, height - MARGIN.top - MARGIN.bottom);

  const { ticks, y, band, subBandWidth, step } = useMemo(() => {
    const maxVal = stacked
      ? Math.max(...timeline.map((d) => d.logins + d.failures + d.registrations), 1)
      : Math.max(...timeline.flatMap((d) => [d.logins, d.failures, d.registrations]), 1);

    const _ticks = niceLinearTicks(0, maxVal, 5, true);
    const yMax = _ticks[_ticks.length - 1];
    const _y = linearScale([0, yMax], [plotH, 0]);
    const _band = bandScale(timeline.map((d) => d.date), [0, plotW], stacked ? 0.15 : 0.2);
    const _subBandWidth = stacked ? _band.bandwidth : _band.bandwidth / SERIES.length;

    const labelWidth = 48;
    const maxLabels = Math.max(1, Math.floor(plotW / labelWidth));
    const _step = Math.max(1, Math.ceil(timeline.length / maxLabels));

    return { ticks: _ticks, y: _y, band: _band, subBandWidth: _subBandWidth, step: _step };
  }, [timeline, plotW, plotH, stacked]);

  const tooltipRows: TooltipRow[] | undefined = hover
    ? SERIES.map((s) => ({ color: s.color, label: s.label, value: hover.entry[s.key] }))
    : undefined;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{__('Auth Activity', 'wp-sms')}</CardTitle>
        <CardDescription>{__('Logins, failures, and registrations over time', 'wp-sms')}</CardDescription>
      </CardHeader>
      <CardContent>
        <div ref={containerRef} className="relative h-72">
          <div ref={ref} className="h-full w-full">
            {width > 0 && (
              <svg width={width} height={height} className="overflow-visible">
                <g transform={`translate(${MARGIN.left},${MARGIN.top})`}>
                  {/* Legend */}
                  {SERIES.map((s, i) => (
                    <g key={s.key} transform={`translate(${i * 110}, -20)`}>
                      <circle cx={6} cy={0} r={5} fill={s.color} />
                      <text x={16} y={0} dominantBaseline="middle" className="fill-foreground text-[11px]">{s.label}</text>
                    </g>
                  ))}

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

                  {/* Hover highlight band */}
                  {hover && (
                    <rect
                      x={band.get(hover.entry.date) - 4}
                      y={0}
                      width={band.bandwidth + 8}
                      height={plotH}
                      rx={4}
                      fill="var(--foreground)"
                      opacity={0.05}
                      className="pointer-events-none"
                    />
                  )}

                  {/* Bars */}
                  {timeline.map((d, di) => {
                    const bandX = band.get(d.date);
                    // Cap total stagger to ~600ms regardless of data size
                    const delay = Math.round((di / Math.max(timeline.length - 1, 1)) * 600);

                    if (stacked) {
                      // Animate the whole stack group as one unit
                      let cumY = plotH;
                      const cx = bandX + band.bandwidth / 2;
                      return (
                        <g
                          key={d.date}
                          style={{ transformOrigin: `${cx}px ${plotH}px`, animation: BAR_ANIMATION, animationDelay: `${delay}ms` }}
                        >
                          {SERIES.map((s) => {
                            const val = d[s.key];
                            const barH = plotH - y(val);
                            cumY -= barH;
                            return (
                              <rect
                                key={s.key}
                                x={bandX}
                                y={cumY}
                                width={band.bandwidth}
                                height={Math.max(0, barH)}
                                rx={2}
                                fill={s.color}
                                onMouseMove={(e) => handleMouseMove(d, e)}
                                onMouseLeave={() => setHover(null)}
                              />
                            );
                          })}
                        </g>
                      );
                    }

                    return (
                      <g key={d.date}>
                        {SERIES.map((s, si) => {
                          const val = d[s.key];
                          const barH = plotH - y(val);
                          const barX = bandX + si * subBandWidth;
                          const cx = barX + subBandWidth / 2;
                          return (
                            <rect
                              key={s.key}
                              x={barX}
                              y={y(val)}
                              width={subBandWidth}
                              height={Math.max(0, barH)}
                              rx={2}
                              fill={s.color}
                              style={{ transformOrigin: `${cx}px ${plotH}px`, animation: BAR_ANIMATION, animationDelay: `${delay + si * 40}ms` }}
                              onMouseMove={(e) => handleMouseMove(d, e)}
                              onMouseLeave={() => setHover(null)}
                            />
                          );
                        })}
                      </g>
                    );
                  })}

                  {/* X axis labels */}
                  {timeline.map((d, i) =>
                    i % step === 0 ? (
                      <text
                        key={d.date}
                        x={band.get(d.date) + band.bandwidth / 2}
                        y={plotH + 16}
                        textAnchor="middle"
                        className="fill-muted-foreground text-[11px]"
                      >
                        {formatTick(d.date)}
                      </text>
                    ) : null,
                  )}
                </g>
              </svg>
            )}
          </div>
          {hover && tooltipRows && (
            <ChartTooltip
              x={hover.x + MARGIN.left}
              y={hover.y + MARGIN.top}
              title={formatTooltipLabel(hover.entry.date)}
              rows={tooltipRows}
              containerRef={containerRef}
            />
          )}
        </div>
      </CardContent>
    </Card>
  );
}
