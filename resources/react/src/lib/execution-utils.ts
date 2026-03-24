import type { StepLog } from '@/lib/api';

export const EXECUTION_STATUS_VARIANTS: Record<string, { variant: 'success' | 'destructive' | 'info' | 'warning' | 'neutral'; label: string }> = {
  completed: { variant: 'success', label: 'Completed' },
  failed: { variant: 'destructive', label: 'Failed' },
  running: { variant: 'info', label: 'Running' },
  pending: { variant: 'warning', label: 'Pending' },
  waiting: { variant: 'warning', label: 'Waiting' },
  cancelled: { variant: 'neutral', label: 'Cancelled' },
};

export function formatElapsed(start: string, end: string | null): string {
  if (!end) return '—';
  return computeDuration(start, end) ?? '—';
}

export interface ProcessedStep {
  nodeId: string;
  type: string;
  status: 'completed' | 'failed' | 'running' | 'retrying';
  startedAt: string | null;
  completedAt: string | null;
  duration: string | null;
  input: Record<string, unknown> | undefined;
  output: Record<string, unknown> | undefined;
  error: string | undefined;
  retries: { attempt: number; maxAttempts: number; error: string }[];
}

export function groupStepLogs(logs: StepLog[]): ProcessedStep[] {
  const grouped = new Map<string, StepLog[]>();

  for (const log of logs) {
    if (!grouped.has(log.node_id)) {
      grouped.set(log.node_id, []);
    }
    grouped.get(log.node_id)!.push(log);
  }

  const result: ProcessedStep[] = [];

  for (const [nodeId, entries] of grouped) {
    const started = entries.find((e) => e.status === 'started');
    const completed = entries.find((e) => e.status === 'completed');
    const failed = entries.find((e) => e.status === 'failed');
    const retrying = entries.filter((e) => e.status === 'retrying');

    let status: ProcessedStep['status'];
    if (completed) status = 'completed';
    else if (failed) status = 'failed';
    else if (retrying.length > 0) status = 'retrying';
    else status = 'running';

    const startedAt = started?.at ?? null;
    const completedAt = (completed ?? failed)?.at ?? null;

    result.push({
      nodeId,
      type: started?.type ?? entries[0]?.type ?? 'unknown',
      status,
      startedAt,
      completedAt,
      duration: computeDuration(startedAt, completedAt),
      input: started?.input,
      output: completed?.output ?? failed?.output,
      error: failed?.error,
      retries: retrying.map((r) => ({
        attempt: r.attempt ?? 0,
        maxAttempts: r.max_attempts ?? 0,
        error: r.error ?? '',
      })),
    });
  }

  return result;
}

export function computeDuration(start: string | null, end: string | null): string | null {
  if (!start || !end) return null;
  const ms = new Date(end).getTime() - new Date(start).getTime();
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  return `${Math.round(ms / 60000)}m`;
}
