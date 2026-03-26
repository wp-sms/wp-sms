import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type SystemHealthResponse } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

export interface UseSystemHealthReturn {
  data: SystemHealthResponse | null;
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

export function useSystemHealth(): UseSystemHealthReturn {
  const [data, setData] = useState<SystemHealthResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController>();
  const hasDataRef = useRef(false);

  const fetch = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    if (!hasDataRef.current) {
      setLoading(true);
    }
    setError(null);
    try {
      const res = await api.get<{ success: boolean; data: SystemHealthResponse }>('system/health', { signal: controller.signal });
      if (!controller.signal.aborted) {
        hasDataRef.current = true;
        setData((prev) =>
          prev?.generated_at === res.data.generated_at ? prev : res.data,
        );
      }
    } catch (e) {
      if (isAbortError(e)) return;
      setError('Failed to load system health data.');
      setData(null);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    void fetch();
    return () => {
      abortRef.current?.abort();
    };
  }, [fetch]);

  return { data, loading, error, refetch: fetch };
}
