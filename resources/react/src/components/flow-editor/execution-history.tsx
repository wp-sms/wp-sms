import { useState } from 'react';
import { useExecutions } from '@/hooks/use-executions';
import type { FlowExecution } from '@/lib/api';
import { EXECUTION_STATUS_VARIANTS, formatElapsed } from '@/lib/execution-utils';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { PageNumbers } from '@/components/ui/pagination';
import { RefreshCw } from 'lucide-react';
import { ExecutionDetailSheet } from './execution-detail-sheet';

interface ExecutionHistoryProps {
  flowId: string;
}

function StatusBadge({ status }: { status: FlowExecution['status'] }) {
  const v = EXECUTION_STATUS_VARIANTS[status] ?? { className: '', label: status };
  return <Badge variant="outline" className={v.className}>{v.label}</Badge>;
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

export function ExecutionHistory({ flowId }: ExecutionHistoryProps) {
  const { executions, total, page, perPage, loading, setPage, refetch } = useExecutions(flowId);
  const totalPages = Math.ceil(total / perPage);
  const [selectedExecution, setSelectedExecution] = useState<FlowExecution | null>(null);

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
            </TableRow>
          </TableHeader>
          <TableBody>
            {executions.map((exec) => (
              <TableRow
                key={exec.id}
                className="even:bg-muted/30 cursor-pointer hover:bg-muted/50"
                onClick={() => setSelectedExecution(exec)}
              >
                <TableCell>
                  <StatusBadge status={exec.status} />
                </TableCell>
                <TableCell className="text-xs font-mono max-w-[200px] truncate">
                  {JSON.stringify(exec.trigger_data).slice(0, 80)}
                </TableCell>
                <TableCell className="text-sm">{formatTime(exec.started_at)}</TableCell>
                <TableCell className="text-sm">
                  {formatElapsed(exec.started_at, exec.completed_at)}
                </TableCell>
                <TableCell className="text-sm text-destructive max-w-[150px] truncate">
                  {exec.error ?? '—'}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <PageNumbers page={page} totalPages={totalPages} onPageChange={setPage} />

      <ExecutionDetailSheet
        execution={selectedExecution}
        onClose={() => setSelectedExecution(null)}
      />
    </div>
  );
}
