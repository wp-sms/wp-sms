import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Flow, type ListResponse } from '@/lib/api';

export interface FlowFilters {
  status: Flow['status'] | '';
}

export interface UseFlowsReturn {
  flows: Flow[];
  total: number;
  page: number;
  perPage: number;
  filters: FlowFilters;
  setFilter: (key: keyof FlowFilters, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
  createFlow: (data: Partial<Flow>) => Promise<Flow>;
  updateFlow: (id: string, data: Partial<Flow>) => Promise<Flow>;
  deleteFlow: (id: string) => Promise<void>;
  publishFlow: (id: string) => Promise<Flow>;
  refetch: () => void;
}

const EMPTY_FILTERS: FlowFilters = { status: '' };

export function useFlows(perPage = 20): UseFlowsReturn {
  const [flows, setFlows] = useState<Flow[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<FlowFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  const fetchFlows = useCallback(async (p: number, f: FlowFilters) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('per_page', String(perPage));
      params.set('offset', String((p - 1) * perPage));
      if (f.status) params.set('status', f.status);

      const res = await api.get<ListResponse<Flow>>(`flows?${params.toString()}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setFlows(res.items);
        setTotal(res.total);
      }
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setFlows([]);
      setTotal(0);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, [perPage]);

  const setFilter = useCallback((key: keyof FlowFilters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  }, []);

  const refetch = useCallback(() => {
    setFetchTrigger((n) => n + 1);
  }, []);

  const createFlow = useCallback(async (data: Partial<Flow>): Promise<Flow> => {
    const res = await api.post<{ success: boolean; data: Flow }>('flows', data);
    refetch();
    return res.data;
  }, [refetch]);

  const updateFlow = useCallback(async (id: string, data: Partial<Flow>): Promise<Flow> => {
    const res = await api.put<{ success: boolean; data: Flow }>(`flows/${id}`, data);
    refetch();
    return res.data;
  }, [refetch]);

  const deleteFlow = useCallback(async (id: string): Promise<void> => {
    await api.del(`flows/${id}`);
    refetch();
  }, [refetch]);

  const publishFlow = useCallback(async (id: string): Promise<Flow> => {
    const res = await api.post<{ success: boolean; data: Flow }>(`flows/${id}/publish`, {});
    refetch();
    return res.data;
  }, [refetch]);

  useEffect(() => {
    clearTimeout(debounceRef.current);
    const hasFilterValue = filters.status;
    debounceRef.current = setTimeout(() => {
      void fetchFlows(page, filters);
    }, page === 1 && hasFilterValue ? 300 : 0);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [page, filters, fetchFlows, fetchTrigger]);

  return { flows, total, page, perPage, filters, setFilter, setPage, loading, createFlow, updateFlow, deleteFlow, publishFlow, refetch };
}
