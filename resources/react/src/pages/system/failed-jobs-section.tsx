import { __ } from '@wordpress/i18n';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { EmptyState } from '@/components/ui/empty-state';
import { useConfirm } from '@/components/confirm-provider';
import { api } from '@/lib/api';
import { formatRelativeTime, formatDateTime } from '@/lib/format';
import { RotateCcw, Trash2, CheckCircle } from 'lucide-react';
import { toast } from 'sonner';
import type { SystemHealthResponse } from '@/lib/api';

interface FailedJobsSectionProps {
  data: SystemHealthResponse['failed_jobs'];
  onMutate: () => void;
}

export function FailedJobsSection({ data, onMutate }: FailedJobsSectionProps) {
  const confirm = useConfirm();
  const [busy, setBusy] = useState<Record<number, boolean>>({});

  async function handleRetry(actionId: number) {
    setBusy((p) => ({ ...p, [actionId]: true }));
    try {
      await api.post(`system/jobs/${actionId}/retry`, {});
      toast.success(__('Job re-queued', 'wp-sms'));
      onMutate();
    } catch {
      toast.error(__('Failed to retry job', 'wp-sms'));
    } finally {
      setBusy((p) => ({ ...p, [actionId]: false }));
    }
  }

  async function handleDismiss(actionId: number) {
    const ok = await confirm({
      title: 'Dismiss failed job',
      description: 'This will permanently delete this failed job entry. This cannot be undone.',
      confirmLabel: 'Dismiss',
      variant: 'destructive',
    });
    if (!ok) return;

    setBusy((p) => ({ ...p, [actionId]: true }));
    try {
      await api.post(`system/jobs/${actionId}/dismiss`, {});
      toast.success(__('Job dismissed', 'wp-sms'));
      onMutate();
    } catch {
      toast.error(__('Failed to dismiss job', 'wp-sms'));
    } finally {
      setBusy((p) => ({ ...p, [actionId]: false }));
    }
  }

  if (data.items.length === 0) {
    return (
      <EmptyState
        icon={CheckCircle}
        title={__('No failed jobs', 'wp-sms')}
        description={__('All background jobs are running smoothly.', 'wp-sms')}
        compact
      />
    );
  }

  return (
    <div className="space-y-4">
      {data.error_groups.length > 1 && (
        <div className="space-y-1">
          <p className="text-xs font-medium text-muted-foreground">{__('Error summary', 'wp-sms')}</p>
          <div className="flex flex-wrap gap-2">
            {data.error_groups.map((g) => (
              <Badge key={g.message} variant="neutral" className="max-w-xs truncate">
                {g.count}x {g.message}
              </Badge>
            ))}
          </div>
        </div>
      )}

      <div className="overflow-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{__('Type', 'wp-sms')}</TableHead>
              <TableHead>{__('Error', 'wp-sms')}</TableHead>
              <TableHead>{__('Severity', 'wp-sms')}</TableHead>
              <TableHead>{__('Failed', 'wp-sms')}</TableHead>
              <TableHead className="text-right">{__('Actions', 'wp-sms')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.items.map((job) => (
              <TableRow key={job.action_id} className="even:bg-muted/30">
                <TableCell className="font-mono text-sm">{job.job_type}</TableCell>
                <TableCell className="max-w-xs">
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <span className="block truncate text-sm">{job.error_message || 'No error message'}</span>
                    </TooltipTrigger>
                    <TooltipContent side="bottom" className="max-w-md break-words">
                      {job.error_message || 'No error message'}
                    </TooltipContent>
                  </Tooltip>
                </TableCell>
                <TableCell>
                  <Badge variant={job.severity === 'high' ? 'destructive' : 'neutral'}>
                    {job.severity}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <span className="cursor-default text-sm tabular-nums">
                        {formatRelativeTime(job.failed_at)}
                      </span>
                    </TooltipTrigger>
                    <TooltipContent>{formatDateTime(job.failed_at)}</TooltipContent>
                  </Tooltip>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex items-center justify-end gap-1">
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      onClick={() => handleRetry(job.action_id)}
                      disabled={busy[job.action_id]}
                    >
                      <RotateCcw className="h-3.5 w-3.5" />
                      <span className="sr-only">Retry</span>
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      onClick={() => handleDismiss(job.action_id)}
                      disabled={busy[job.action_id]}
                    >
                      <Trash2 className="h-3.5 w-3.5 text-muted-foreground" />
                      <span className="sr-only">Dismiss</span>
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {data.total > data.items.length && (
        <p className="text-xs text-muted-foreground text-center">
          Showing {data.items.length} of {data.total} failed jobs
        </p>
      )}
    </div>
  );
}
