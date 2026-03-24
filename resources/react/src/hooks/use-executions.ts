import { type FlowExecution } from '@/lib/api';
import { useListResource } from './use-list-resource';

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
  const list = useListResource<FlowExecution, Record<string, string>>({
    endpoint: `flows/${flowId}/executions`,
    defaultFilters: {},
    perPage,
    enabled: !!flowId,
  });

  return {
    executions: list.items, total: list.total, page: list.page,
    perPage: list.perPage, loading: list.loading, setPage: list.setPage, refetch: list.refetch,
  };
}
