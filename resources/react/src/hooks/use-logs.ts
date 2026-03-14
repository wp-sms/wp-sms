import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type LogEntry, type LogsResponse } from '@/lib/api';

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

const EMPTY_FILTERS: LogFilters = { event: '', status: '', user_id: '', date_from: '', date_to: '' };

export function useLogs(perPage = 20): UseLogsReturn {
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<LogFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
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
      if (f.date_from) params.set('date_from', f.date_from);
      if (f.date_to) params.set('date_to', f.date_to);

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

  const clearLogs = useCallback(async () => {
    await api.del('auth/admin/logs');
    setPage(1);
    // Bump trigger to force re-fetch even if page was already 1.
    setFetchTrigger((n) => n + 1);
  }, []);

  // Fetch on mount and when page/filters/trigger change
  useEffect(() => {
    clearTimeout(debounceRef.current);
    const hasFilterValue = filters.event || filters.status || filters.user_id || filters.date_from || filters.date_to;
    debounceRef.current = setTimeout(() => {
      void fetchLogs(page, filters);
    }, page === 1 && hasFilterValue ? 300 : 0);

    return () => clearTimeout(debounceRef.current);
  }, [page, filters, fetchLogs, fetchTrigger]);

  return { logs, total, page, perPage, filters, setFilter, setPage, loading, clearLogs };
}
