import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type MessageLogEntry, type ListResponse } from '@/lib/api';

export interface MessageLogFilters {
  channel: string;
  status: string;
  recipient: string;
  gateway_id: string;
}

export interface UseMessageLogsReturn {
  logs: MessageLogEntry[];
  total: number;
  page: number;
  perPage: number;
  filters: MessageLogFilters;
  setFilter: (key: keyof MessageLogFilters, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
}

const EMPTY_FILTERS: MessageLogFilters = { channel: '', status: '', recipient: '', gateway_id: '' };

export function useMessageLogs(perPage = 20): UseMessageLogsReturn {
  const [logs, setLogs] = useState<MessageLogEntry[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<MessageLogFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  const fetchLogs = useCallback(async (p: number, f: MessageLogFilters) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('per_page', String(perPage));
      params.set('offset', String((p - 1) * perPage));
      if (f.channel) params.set('channel', f.channel);
      if (f.status) params.set('status', f.status);
      if (f.recipient) params.set('recipient', f.recipient);
      if (f.gateway_id) params.set('gateway_id', f.gateway_id);

      const res = await api.get<ListResponse<MessageLogEntry>>(`message-logs?${params.toString()}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setLogs(res.items);
        setTotal(res.total);
      }
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setLogs([]);
      setTotal(0);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, [perPage]);

  const setFilter = useCallback((key: keyof MessageLogFilters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  }, []);

  useEffect(() => {
    clearTimeout(debounceRef.current);
    const hasFilterValue = filters.channel || filters.status || filters.recipient || filters.gateway_id;
    debounceRef.current = setTimeout(() => {
      void fetchLogs(page, filters);
    }, page === 1 && hasFilterValue ? 300 : 0);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [page, filters, fetchLogs]);

  return { logs, total, page, perPage, filters, setFilter, setPage, loading };
}
