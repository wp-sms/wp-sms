import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatRelativeTime, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SystemHealthResponse } from '@/lib/api';

const DOT_COLORS: Record<string, string> = {
  complete: 'bg-emerald-500',
  failed: 'bg-destructive',
};

interface ActivityGroup {
  job_type: string;
  hook: string;
  status: string;
  count: number;
  earliest: string;
  latest: string;
}

function groupConsecutive(entries: SystemHealthResponse['recent_activity']): ActivityGroup[] {
  if (entries.length === 0) return [];

  const groups: ActivityGroup[] = [];
  let current: ActivityGroup = {
    job_type: entries[0].job_type,
    hook: entries[0].hook,
    status: entries[0].status,
    count: 1,
    earliest: entries[0].completed_at,
    latest: entries[0].completed_at,
  };

  for (let i = 1; i < entries.length; i++) {
    const entry = entries[i];
    if (entry.job_type === current.job_type && entry.status === current.status) {
      current.count++;
      current.earliest = entry.completed_at; // entries are DESC, so later items are earlier in time
    } else {
      groups.push(current);
      current = {
        job_type: entry.job_type,
        hook: entry.hook,
        status: entry.status,
        count: 1,
        earliest: entry.completed_at,
        latest: entry.completed_at,
      };
    }
  }
  groups.push(current);
  return groups;
}

interface ActivityFeedProps {
  data: SystemHealthResponse['recent_activity'];
}

export function ActivityFeed({ data }: ActivityFeedProps) {
  const groups = useMemo(() => groupConsecutive(data), [data]);

  if (groups.length === 0) {
    return (
      <p className="py-6 text-center text-sm text-muted-foreground">
        No recent activity
      </p>
    );
  }

  return (
    <div className="relative space-y-0">
      {groups.map((group, i) => {
        const dotColor = DOT_COLORS[group.status] ?? 'bg-gray-400';
        const isLast = i === groups.length - 1;
        const isFailed = group.status === 'failed';

        return (
          <div key={`${group.job_type}-${group.status}-${i}`} className="relative flex gap-3 pb-3 last:pb-0">
            {!isLast && (
              <div className="absolute left-[5px] top-3 bottom-0 w-px bg-border" />
            )}
            <div className="relative mt-1.5 flex-shrink-0">
              <span className={cn('block h-2.5 w-2.5 rounded-full', dotColor)} />
            </div>
            <div className="flex flex-1 items-center justify-between gap-2 min-w-0">
              <div className="flex items-center gap-2 min-w-0">
                <p className={cn('text-sm font-medium truncate', isFailed && 'text-destructive')}>
                  {group.job_type}
                </p>
                {group.count > 1 && (
                  <Badge variant="neutral" className="shrink-0 text-[10px] px-1.5">
                    &times;{group.count}
                  </Badge>
                )}
              </div>
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="cursor-default text-xs tabular-nums text-muted-foreground shrink-0">
                    {formatRelativeTime(group.latest)}
                  </span>
                </TooltipTrigger>
                <TooltipContent>
                  {group.count > 1
                    ? `${group.count} executions between ${formatDateTime(group.earliest)} and ${formatDateTime(group.latest)}`
                    : formatDateTime(group.latest)}
                </TooltipContent>
              </Tooltip>
            </div>
          </div>
        );
      })}
    </div>
  );
}
