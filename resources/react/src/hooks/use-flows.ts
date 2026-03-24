import { useCallback } from 'react';
import { api, type Flow } from '@/lib/api';
import { useListResource } from './use-list-resource';

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

export function useFlows(perPage = 20): UseFlowsReturn {
  const list = useListResource<Flow, FlowFilters>({
    endpoint: 'flows',
    defaultFilters: { status: '' },
    perPage,
  });

  const createFlow = useCallback(async (data: Partial<Flow>): Promise<Flow> => {
    const res = await api.post<{ success: boolean; data: Flow }>('flows', data);
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const updateFlow = useCallback(async (id: string, data: Partial<Flow>): Promise<Flow> => {
    const res = await api.put<{ success: boolean; data: Flow }>(`flows/${id}`, data);
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const deleteFlow = useCallback(async (id: string): Promise<void> => {
    await api.del(`flows/${id}`);
    list.refetch();
  }, [list.refetch]);

  const publishFlow = useCallback(async (id: string): Promise<Flow> => {
    const res = await api.post<{ success: boolean; data: Flow }>(`flows/${id}/publish`, {});
    list.refetch();
    return res.data;
  }, [list.refetch]);

  return {
    flows: list.items, total: list.total, page: list.page, perPage: list.perPage,
    filters: list.filters, setFilter: list.setFilter, setPage: list.setPage, loading: list.loading,
    createFlow, updateFlow, deleteFlow, publishFlow, refetch: list.refetch,
  };
}
