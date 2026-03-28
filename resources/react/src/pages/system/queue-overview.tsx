import { __ } from '@wordpress/i18n';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Clock, Loader2, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SystemHealthResponse } from '@/lib/api';

interface QueueOverviewProps {
  data: SystemHealthResponse['queue'];
}

function StatCard({ label, value, icon: Icon, colorClass, delay, muted }: {
  label: string;
  value: number;
  icon: React.ComponentType<{ className?: string }>;
  colorClass: string;
  delay: number;
  muted?: boolean;
}) {
  return (
    <div
      className={cn('animate-fade-up rounded-lg border bg-card p-4', muted && 'opacity-50')}
      style={{ animationDelay: `${delay}ms` }}
    >
      <div className="flex items-center gap-2">
        <Icon className={cn('h-4 w-4', colorClass)} />
        <span className="text-xs text-muted-foreground">{label}</span>
      </div>
      <p className="mt-1 text-xl font-semibold tabular-nums">{value.toLocaleString()}</p>
    </div>
  );
}

export function QueueOverview({ data }: QueueOverviewProps) {
  const pending    = data.totals['pending'] ?? 0;
  const inProgress = data.totals['in-progress'] ?? 0;
  const failed     = data.totals['failed'] ?? 0;

  return (
    <div className="space-y-4">
      <div className="grid gap-3 grid-cols-3">
        <StatCard label="Pending" value={pending} icon={Clock} colorClass="text-blue-500" delay={0} muted={pending === 0} />
        <StatCard label="In Progress" value={inProgress} icon={Loader2} colorClass="text-amber-500" delay={60} muted={inProgress === 0} />
        <StatCard label="Failed" value={failed} icon={XCircle} colorClass="text-destructive" delay={120} muted={failed === 0} />
      </div>

      {data.stuck_jobs.length > 0 && (
        <div className="flex items-center gap-2.5 rounded-lg border border-warning/40 bg-warning/[0.06] px-4 py-2.5 text-sm text-warning">
          <span className="relative flex h-2 w-2 shrink-0">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-warning opacity-75" />
            <span className="relative inline-flex h-2 w-2 rounded-full bg-warning" />
          </span>
          <span>{data.stuck_jobs.length} possibly stuck {data.stuck_jobs.length === 1 ? 'job' : 'jobs'} (in-progress for &gt;5 min)</span>
        </div>
      )}

      {data.by_type.length > 0 && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{__('Type', 'wp-sms')}</TableHead>
              <TableHead className="text-right">{__('Pending', 'wp-sms')}</TableHead>
              <TableHead className="text-right">{__('In Progress', 'wp-sms')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.by_type.map((row) => (
              <TableRow key={row.type} className="even:bg-muted/30">
                <TableCell className="font-mono text-sm">{row.type}</TableCell>
                <TableCell className="text-right tabular-nums">{row.pending}</TableCell>
                <TableCell className="text-right tabular-nums">
                  {row.in_progress}
                  {data.stuck_jobs.some((s) => s.job_type === row.type) && (
                    <Badge variant="warning" className="ml-2">stuck</Badge>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  );
}
