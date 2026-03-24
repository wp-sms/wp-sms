import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type ReportsResponse } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

export interface UseReportsReturn {
  data: ReportsResponse | null;
  loading: boolean;
  error: string | null;
  range: number;
  setRange: (range: number) => void;
}

export function useReports(initialRange = 30): UseReportsReturn {
  const [data, setData] = useState<ReportsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [range, setRange] = useState(initialRange);
  const abortRef = useRef<AbortController>();

  const fetchReports = useCallback(async (r: number) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setError(null);
    try {
      const res = await api.get<ReportsResponse>(`auth/admin/reports?range=${r}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setData(res);
      }
    } catch (e) {
      if (isAbortError(e)) return;
      setError('Failed to load reports.');
      setData(null);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    void fetchReports(range);
    return () => {
      abortRef.current?.abort();
    };
  }, [range, fetchReports]);

  return { data, loading, error, range, setRange };
}
