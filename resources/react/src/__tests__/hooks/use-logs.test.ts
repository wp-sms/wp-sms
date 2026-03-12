import { describe, it, expect } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useLogs } from '@/hooks/use-logs';

describe('useLogs', () => {
  it('fetches logs on mount', async () => {
    const { result } = renderHook(() => useLogs());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    expect(result.current.logs.length).toBeGreaterThan(0);
    expect(result.current.total).toBe(3);
    expect(result.current.page).toBe(1);
  });

  it('filters logs by event type', async () => {
    const { result } = renderHook(() => useLogs());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.setFilter('event', 'login_success');
    });

    // Wait for the debounced fetch to complete with filtered results
    await waitFor(() => {
      expect(result.current.total).toBe(1);
    });

    expect(result.current.logs[0]?.event).toBe('login_success');
  });

  it('filters logs by status', async () => {
    const { result } = renderHook(() => useLogs());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.setFilter('status', 'failure');
    });

    // Wait for the debounced fetch to complete with filtered results
    await waitFor(() => {
      expect(result.current.total).toBe(1);
    });

    expect(result.current.logs[0]?.status).toBe('failure');
  });

  it('resets to page 1 when filter changes', async () => {
    const { result } = renderHook(() => useLogs());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.setPage(2);
    });

    act(() => {
      result.current.setFilter('event', 'login_success');
    });

    expect(result.current.page).toBe(1);
  });

  it('changes page', async () => {
    const { result } = renderHook(() => useLogs());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.setPage(2);
    });

    expect(result.current.page).toBe(2);
  });
});
