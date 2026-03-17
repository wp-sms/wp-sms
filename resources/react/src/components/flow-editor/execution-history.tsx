import { useState } from 'react';
import { useExecutions } from '@/hooks/use-executions';
import type { FlowExecution, StepLog } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';
import { ChevronDown, RefreshCw } from 'lucide-react';

interface ExecutionHistoryProps {
  flowId: string;
}

function StatusBadge({ status }: { status: FlowExecution['status'] }) {
  const variants: Record<FlowExecution['status'], { className: string; label: string }> = {
    completed: { className: 'border-emerald-200 bg-emerald-50 text-emerald-700', label: 'Completed' },
    failed: { className: 'border-red-200 bg-red-50 text-red-700', label: 'Failed' },
    running: { className: 'border-blue-200 bg-blue-50 text-blue-700', label: 'Running' },
    pending: { className: 'border-amber-200 bg-amber-50 text-amber-700', label: 'Pending' },
    waiting: { className: 'border-amber-200 bg-amber-50 text-amber-700', label: 'Waiting' },
  };

  const v = variants[status] ?? { className: '', label: status };
  return <Badge variant="outline" className={v.className}>{v.label}</Badge>;
}

function StepLogBadge({ status }: { status: StepLog['status'] }) {
  const colors: Record<StepLog['status'], string> = {
    started: 'text-blue-600',
    completed: 'text-emerald-600',
    error: 'text-red-600',
  };
  return <span className={`text-xs font-medium ${colors[status]}`}>{status}</span>;
}

function formatDuration(start: string, end: string | null): string {
  if (!end) return '—';
  const ms = new Date(end).getTime() - new Date(start).getTime();
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  return `${Math.round(ms / 60000)}m`;
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function ExecutionRow({ execution }: { execution: FlowExecution }) {
  const [open, setOpen] = useState(false);
  const hasLogs = execution.step_logs.length > 0;

  return (
    <Collapsible open={open} onOpenChange={setOpen}>
      <TableRow className="even:bg-muted/30">
        <TableCell>
          <StatusBadge status={execution.status} />
        </TableCell>
        <TableCell className="text-xs font-mono max-w-[200px] truncate">
          {JSON.stringify(execution.trigger_data).slice(0, 80)}
        </TableCell>
        <TableCell className="text-sm">{formatTime(execution.started_at)}</TableCell>
        <TableCell className="text-sm">
          {formatDuration(execution.started_at, execution.completed_at)}
        </TableCell>
        <TableCell className="text-sm text-destructive max-w-[150px] truncate">
          {execution.error ?? '—'}
        </TableCell>
        <TableCell>
          {hasLogs && (
            <CollapsibleTrigger asChild>
              <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                <ChevronDown className={`h-3.5 w-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />
              </Button>
            </CollapsibleTrigger>
          )}
        </TableCell>
      </TableRow>

      {hasLogs && (
        <CollapsibleContent asChild>
          <tr>
            <td colSpan={6} className="p-0">
              <div className="border-t bg-muted/20 px-6 py-3">
                <p className="mb-2 text-xs font-medium text-muted-foreground">Step Log Timeline</p>
                <div className="space-y-1">
                  {execution.step_logs.map((log, i) => (
                    <div key={i} className="flex items-center gap-3 text-xs">
                      <span className="w-16 shrink-0 text-muted-foreground">
                        {new Date(log.at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                      </span>
                      <StepLogBadge status={log.status} />
                      <span className="font-mono text-muted-foreground">{log.node_id}</span>
                      <span className="text-muted-foreground">({log.type})</span>
                      {log.error && (
                        <span className="text-destructive truncate">{log.error}</span>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            </td>
          </tr>
        </CollapsibleContent>
      )}
    </Collapsible>
  );
}

export function ExecutionHistory({ flowId }: ExecutionHistoryProps) {
  const { executions, total, page, perPage, loading, setPage, refetch } = useExecutions(flowId);
  const totalPages = Math.ceil(total / perPage);

  if (loading) {
    return (
      <div className="space-y-3">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-12 w-full" />
        ))}
      </div>
    );
  }

  if (executions.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <p className="text-sm font-medium">No executions yet</p>
        <p className="mt-1 text-xs text-muted-foreground">
          Executions will appear here after the flow is triggered.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{total} execution{total !== 1 ? 's' : ''}</p>
        <Button variant="ghost" size="sm" onClick={refetch}>
          <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
          Refresh
        </Button>
      </div>

      <div className="rounded-lg border border-border/50 overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Status</TableHead>
              <TableHead>Trigger Data</TableHead>
              <TableHead>Started</TableHead>
              <TableHead>Duration</TableHead>
              <TableHead>Error</TableHead>
              <TableHead className="w-10" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {executions.map((exec) => (
              <ExecutionRow key={exec.id} execution={exec} />
            ))}
          </TableBody>
        </Table>
      </div>

      {totalPages > 1 && (
        <div className="flex justify-center">
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  onClick={() => setPage(Math.max(1, page - 1))}
                  className={page <= 1 ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                />
              </PaginationItem>
              {Array.from({ length: Math.min(5, totalPages) }).map((_, i) => {
                let pageNum: number;
                if (totalPages <= 5) {
                  pageNum = i + 1;
                } else if (page <= 3) {
                  pageNum = i + 1;
                } else if (page >= totalPages - 2) {
                  pageNum = totalPages - 4 + i;
                } else {
                  pageNum = page - 2 + i;
                }
                return (
                  <PaginationItem key={pageNum}>
                    <PaginationLink
                      onClick={() => setPage(pageNum)}
                      isActive={page === pageNum}
                      className="cursor-pointer"
                    >
                      {pageNum}
                    </PaginationLink>
                  </PaginationItem>
                );
              })}
              <PaginationItem>
                <PaginationNext
                  onClick={() => setPage(Math.min(totalPages, page + 1))}
                  className={page >= totalPages ? 'pointer-events-none opacity-50' : 'cursor-pointer'}
                />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        </div>
      )}
    </div>
  );
}
