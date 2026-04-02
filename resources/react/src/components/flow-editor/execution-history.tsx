import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import { useExecutions } from '@/hooks/use-executions';
import type { FlowExecution } from '@/lib/api';
import { formatElapsed } from '@/lib/execution-utils';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { DataTable } from '@/components/ui/data-table';
import { ExecutionStatusBadge } from './execution-status-badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { RefreshCw } from 'lucide-react';
import { ExecutionDetailPanel } from './execution-detail-panel';
import { formatDateTime } from '@/lib/format';

interface ExecutionHistoryProps {
  flowId: string;
}

export function ExecutionHistory({ flowId }: ExecutionHistoryProps) {
  const { executions, total, page, perPage, loading, setPage, refetch } = useExecutions(flowId);
  const totalPages = Math.ceil(total / perPage);
  const [selectedExecution, setSelectedExecution] = useState<FlowExecution | null>(null);

  return (
    <div className="space-y-4">
      {!loading && executions.length > 0 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-muted-foreground">{total} {total !== 1 ? __('executions', 'wp-sms') : __('execution', 'wp-sms')}</p>
          <Button variant="ghost" size="sm" onClick={refetch}>
            <RefreshCw className="me-1.5 h-3.5 w-3.5" />
            {__('Refresh', 'wp-sms')}
          </Button>
        </div>
      )}

      <DataTable
        loading={loading}
        isEmpty={executions.length === 0}
        empty={
          <EmptyState
            compact
            title={__('No executions yet', 'wp-sms')}
            description={__('Executions will appear here after the flow is triggered.', 'wp-sms')}
          />
        }
        pagination={{ page, totalPages, onPageChange: setPage }}
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{__('Status', 'wp-sms')}</TableHead>
              <TableHead>{__('Trigger Data', 'wp-sms')}</TableHead>
              <TableHead>{__('Started', 'wp-sms')}</TableHead>
              <TableHead>{__('Duration', 'wp-sms')}</TableHead>
              <TableHead>{__('Error', 'wp-sms')}</TableHead>
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
                  <ExecutionStatusBadge status={exec.status} />
                </TableCell>
                <TableCell className="text-xs font-mono max-w-[200px] truncate">
                  {JSON.stringify(exec.trigger_data).slice(0, 80)}
                </TableCell>
                <TableCell className="text-sm">{formatDateTime(exec.started_at)}</TableCell>
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
      </DataTable>

      <ExecutionDetailPanel
        execution={selectedExecution}
        onClose={() => setSelectedExecution(null)}
      />
    </div>
  );
}
