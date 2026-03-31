import { useState, useEffect, useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { api, type DashboardSummaryResponse } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';
import { usePolling } from '@/hooks/use-polling';

export interface UseDashboardReturn {
  data: DashboardSummaryResponse | null;
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

export function useDashboard(): UseDashboardReturn {
  const [data, setData] = useState<DashboardSummaryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController>();

  const fetchDashboard = useCallback(async (showLoading: boolean) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    if (showLoading) {
      setLoading(true);
    }
    setError(null);
    try {
      const res = await api.get<{ success: boolean; data: DashboardSummaryResponse }>(
        'dashboard/summary?range=30',
        { signal: controller.signal },
      );
      if (!controller.signal.aborted) {
        setData(res.data);
      }
    } catch (e) {
      if (isAbortError(e)) return;
      if (showLoading) {
        setError(__('Failed to load dashboard data.', 'wp-sms'));
        setData(null);
      }
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    void fetchDashboard(true);
    return () => {
      abortRef.current?.abort();
    };
  }, [fetchDashboard]);

  const refetch = useCallback(() => {
    void fetchDashboard(true);
  }, [fetchDashboard]);

  const poll = useCallback(() => {
    void fetchDashboard(false);
  }, [fetchDashboard]);

  usePolling(poll, 60_000, !loading);

  return { data, loading, error, refetch };
}
