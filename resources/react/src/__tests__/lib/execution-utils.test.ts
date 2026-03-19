import { describe, it, expect } from 'vitest';
import { groupStepLogs } from '@/lib/execution-utils';
import type { StepLog } from '@/lib/api';

describe('groupStepLogs', () => {
  it('groups flat logs by node_id preserving order', () => {
    const logs: StepLog[] = [
      { node_id: 'step_1', type: 'action', status: 'started', input: { action: 'send_sms' }, at: '2026-03-19T10:00:00Z' },
      { node_id: 'step_1', type: 'action', status: 'completed', output: { done: true }, at: '2026-03-19T10:00:01Z' },
      { node_id: 'step_2', type: 'condition', status: 'started', input: { expression: 'true' }, at: '2026-03-19T10:00:01Z' },
      { node_id: 'step_2', type: 'condition', status: 'completed', output: { result: true }, at: '2026-03-19T10:00:01Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result).toHaveLength(2);
    expect(result[0].nodeId).toBe('step_1');
    expect(result[1].nodeId).toBe('step_2');
  });

  it('sets correct status for completed steps', () => {
    const logs: StepLog[] = [
      { node_id: 'n1', type: 'action', status: 'started', at: '2026-03-19T10:00:00Z' },
      { node_id: 'n1', type: 'action', status: 'completed', output: {}, at: '2026-03-19T10:00:02Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result[0].status).toBe('completed');
  });

  it('sets correct status for failed steps', () => {
    const logs: StepLog[] = [
      { node_id: 'n1', type: 'action', status: 'started', at: '2026-03-19T10:00:00Z' },
      { node_id: 'n1', type: 'action', status: 'failed', error: 'timeout', at: '2026-03-19T10:00:05Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result[0].status).toBe('failed');
    expect(result[0].error).toBe('timeout');
  });

  it('handles retries correctly', () => {
    const logs: StepLog[] = [
      { node_id: 'n1', type: 'action', status: 'started', at: '2026-03-19T10:00:00Z' },
      { node_id: 'n1', type: 'action', status: 'retrying', attempt: 1, max_attempts: 3, error: 'err1', at: '2026-03-19T10:00:01Z' },
      { node_id: 'n1', type: 'action', status: 'retrying', attempt: 2, max_attempts: 3, error: 'err2', at: '2026-03-19T10:00:02Z' },
      { node_id: 'n1', type: 'action', status: 'completed', output: { ok: true }, at: '2026-03-19T10:00:03Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result[0].status).toBe('completed');
    expect(result[0].retries).toHaveLength(2);
    expect(result[0].retries[0].attempt).toBe(1);
    expect(result[0].retries[1].error).toBe('err2');
  });

  it('computes duration from started to completed', () => {
    const logs: StepLog[] = [
      { node_id: 'n1', type: 'action', status: 'started', at: '2026-03-19T10:00:00.000Z' },
      { node_id: 'n1', type: 'action', status: 'completed', at: '2026-03-19T10:00:02.500Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result[0].duration).toBe('2.5s');
  });

  it('returns null duration when step has no completion', () => {
    const logs: StepLog[] = [
      { node_id: 'n1', type: 'action', status: 'started', at: '2026-03-19T10:00:00Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result[0].status).toBe('running');
    expect(result[0].duration).toBeNull();
  });

  it('captures input from started and output from completed', () => {
    const logs: StepLog[] = [
      { node_id: 'n1', type: 'action', status: 'started', input: { action: 'send_sms', resolved_config: { to: '+1' } }, at: '2026-03-19T10:00:00Z' },
      { node_id: 'n1', type: 'action', status: 'completed', output: { message_id: 'abc' }, at: '2026-03-19T10:00:01Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result[0].input?.resolved_config).toEqual({ to: '+1' });
    expect(result[0].output?.message_id).toBe('abc');
  });

  it('handles interleaved parallel branch logs', () => {
    const logs: StepLog[] = [
      { node_id: 'branch_0', type: 'action', status: 'started', at: '2026-03-19T10:00:00Z' },
      { node_id: 'branch_1', type: 'action', status: 'started', at: '2026-03-19T10:00:00Z' },
      { node_id: 'branch_0', type: 'action', status: 'completed', at: '2026-03-19T10:00:01Z' },
      { node_id: 'branch_1', type: 'action', status: 'completed', at: '2026-03-19T10:00:02Z' },
    ];

    const result = groupStepLogs(logs);
    expect(result).toHaveLength(2);
    expect(result[0].nodeId).toBe('branch_0');
    expect(result[1].nodeId).toBe('branch_1');
    expect(result[0].status).toBe('completed');
    expect(result[1].status).toBe('completed');
  });

  it('returns empty array for empty logs', () => {
    expect(groupStepLogs([])).toEqual([]);
  });
});
