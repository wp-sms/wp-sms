import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type LogEntry, type LogsResponse } from '@/lib/api';

export interface LogFilters {
  event: string;
  status: string;
  user_id: string;
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
}

const EMPTY_FILTERS: LogFilters = { event: '', status: '', user_id: '' };

export function useLogs(perPage = 20): UseLogsReturn {
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<LogFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();

  const fetchLogs = useCallback(async (p: number, f: LogFilters) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', String(p));
      params.set('per_page', String(perPage));
      if (f.event) params.set('event', f.event);
      if (f.status) params.set('status', f.status);
      if (f.user_id) params.set('user_id', f.user_id);

      const res = await api.get<LogsResponse>(`auth/admin/logs?${params.toString()}`);
      setLogs(res.items);
      setTotal(res.total);
    } catch {
      setLogs([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  }, [perPage]);

  // Debounced filter changes
  const setFilter = useCallback((key: keyof LogFilters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  }, []);

  // Fetch on mount and when page changes (immediate)
  useEffect(() => {
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      void fetchLogs(page, filters);
    }, page === 1 && (filters.event || filters.status || filters.user_id) ? 300 : 0);

    return () => clearTimeout(debounceRef.current);
  }, [page, filters, fetchLogs]);

  return { logs, total, page, perPage, filters, setFilter, setPage, loading };
}
