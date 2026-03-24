import { useCallback } from 'react';
import { api, type LogEntry } from '@/lib/api';
import { useListResource } from './use-list-resource';

export interface LogFilters {
  event: string;
  status: string;
  user_id: string;
  date_from: string;
  date_to: string;
}

export interface UseLogsReturn {
  logs: LogEntry[];
  total: number;
  page: number;
  perPage: number;
  filters: LogFilters;
  setFilter: (key: keyof LogFilters, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
  clearLogs: () => Promise<void>;
}

export function useLogs(perPage = 20): UseLogsReturn {
  const list = useListResource<LogEntry, LogFilters>({
    endpoint: 'auth/admin/logs',
    defaultFilters: { event: '', status: '', user_id: '', date_from: '', date_to: '' },
    perPage,
    paginationMode: 'page',
  });

  const clearLogs = useCallback(async () => {
    await api.del('auth/admin/logs');
    list.setPage(1);
    list.refetch();
  }, [list.setPage, list.refetch]);

  return {
    logs: list.items, total: list.total, page: list.page, perPage: list.perPage,
    filters: list.filters, setFilter: list.setFilter, setPage: list.setPage, loading: list.loading,
    clearLogs,
  };
}
