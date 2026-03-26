import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatRelativeTime, formatFutureRelativeTime, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SystemHealthResponse } from '@/lib/api';

const STATUS_VARIANT = {
  healthy: 'success',
  late: 'warning',
  stopped: 'destructive',
  unknown: 'neutral',
} as const;

const STATUS_LABEL = {
  healthy: 'Healthy',
  late: 'Late',
  stopped: 'Stopped',
  unknown: 'Unknown',
} as const;

const STATUS_DOT_COLOR = {
  healthy: 'bg-emerald-500',
  late: 'bg-amber-500',
  stopped: 'bg-destructive',
  unknown: 'bg-gray-400',
} as const;

interface HeartbeatSectionProps {
  data: SystemHealthResponse['heartbeat'];
}

export function HeartbeatSection({ data }: HeartbeatSectionProps) {
  if (data.length === 0) return null;

  return (
    <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
      {data.map((task, i) => (
        <div
          key={task.hook}
          className="animate-fade-up rounded-lg border bg-card p-4"
          style={{ animationDelay: `${i * 60}ms` }}
        >
          <div className="flex items-start justify-between gap-2">
            <div className="flex items-center gap-2 min-w-0">
              {task.status === 'late' ? (
                <span className="relative flex h-2 w-2 shrink-0">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75" />
                  <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500" />
                </span>
              ) : (
                <span className={cn('h-2 w-2 shrink-0 rounded-full', STATUS_DOT_COLOR[task.status])} />
              )}
              <p className="text-sm font-medium leading-tight truncate">{task.label}</p>
            </div>
            <Badge variant={STATUS_VARIANT[task.status]} className="shrink-0 text-[10px]">
              {STATUS_LABEL[task.status]}
            </Badge>
          </div>

          <div className="mt-3 space-y-1 text-xs text-muted-foreground">
            <div className="flex justify-between">
              <span>Last run</span>
              {task.last_run ? (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <span className="cursor-default tabular-nums">
                      {formatRelativeTime(task.last_run)}
                    </span>
                  </TooltipTrigger>
                  <TooltipContent>{formatDateTime(task.last_run)}</TooltipContent>
                </Tooltip>
              ) : (
                <span>Never</span>
              )}
            </div>
            <div className="flex justify-between">
              <span>Next run</span>
              {task.next_run ? (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <span className="cursor-default tabular-nums">
                      {formatFutureRelativeTime(task.next_run)}
                    </span>
                  </TooltipTrigger>
                  <TooltipContent>{formatDateTime(task.next_run)}</TooltipContent>
                </Tooltip>
              ) : (
                <span className="text-destructive">Not scheduled</span>
              )}
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
