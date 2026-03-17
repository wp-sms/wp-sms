import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type FlowExecution, type ListResponse } from '@/lib/api';

export interface UseExecutionsReturn {
  executions: FlowExecution[];
  total: number;
  page: number;
  perPage: number;
  loading: boolean;
  setPage: (page: number) => void;
  refetch: () => void;
}

export function useExecutions(flowId: string, perPage = 10): UseExecutionsReturn {
  const [executions, setExecutions] = useState<FlowExecution[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const abortRef = useRef<AbortController>(null);

  const refetch = useCallback(() => {
    setFetchTrigger((n) => n + 1);
  }, []);

  useEffect(() => {
    if (!flowId) {
      setExecutions([]);
      setTotal(0);
      setLoading(false);
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);

    const params = new URLSearchParams();
    params.set('per_page', String(perPage));
    params.set('offset', String((page - 1) * perPage));

    api.get<ListResponse<FlowExecution>>(`flows/${flowId}/executions?${params.toString()}`, { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setExecutions(res.items);
          setTotal(res.total);
        }
      })
      .catch((e) => {
        if (e instanceof DOMException && e.name === 'AbortError') return;
        setExecutions([]);
        setTotal(0);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => { controller.abort(); };
  }, [flowId, page, perPage, fetchTrigger]);

  return { executions, total, page, perPage, loading, setPage, refetch };
}
