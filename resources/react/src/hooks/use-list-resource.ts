import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type ListResponse } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

export interface ListResourceConfig<F extends Record<string, string>> {
  endpoint: string;
  defaultFilters: F;
  perPage?: number;
  paginationMode?: 'offset' | 'page';
  debounceMs?: number;
  enabled?: boolean;
}

export interface ListResourceReturn<T, F extends Record<string, string>> {
  items: T[];
  total: number;
  page: number;
  perPage: number;
  filters: F;
  setFilter: (key: keyof F, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
  refetch: () => void;
}

export function useListResource<T, F extends Record<string, string>>(
  config: ListResourceConfig<F>,
): ListResourceReturn<T, F> {
  const {
    endpoint,
    defaultFilters,
    perPage = 20,
    paginationMode = 'offset',
    debounceMs = 300,
    enabled = true,
  } = config;

  const [items, setItems] = useState<T[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<F>(defaultFilters);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  const fetchData = useCallback(async (p: number, f: F) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('per_page', String(perPage));
      if (paginationMode === 'page') {
        params.set('page', String(p));
      } else {
        params.set('offset', String((p - 1) * perPage));
      }
      for (const [key, value] of Object.entries(f)) {
        if (value) params.set(key, value);
      }

      const res = await api.get<ListResponse<T>>(`${endpoint}?${params.toString()}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setItems(res.items);
        setTotal(res.total);
      }
    } catch (e) {
      if (isAbortError(e)) return;
      setItems([]);
      setTotal(0);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, [endpoint, perPage, paginationMode]);

  const setFilter = useCallback((key: keyof F, value: string) => {
    setFilters((prev) => prev[key] === value ? prev : { ...prev, [key]: value });
    setPage(1);
  }, []);

  const refetch = useCallback(() => {
    setFetchTrigger((n) => n + 1);
  }, []);

  useEffect(() => {
    if (!enabled) {
      setItems([]);
      setTotal(0);
      setLoading(false);
      return;
    }

    clearTimeout(debounceRef.current);
    const hasFilterValue = Object.values(filters).some(Boolean);
    debounceRef.current = setTimeout(() => {
      void fetchData(page, filters);
    }, page === 1 && hasFilterValue ? debounceMs : 0);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [page, filters, fetchData, fetchTrigger, enabled, debounceMs]);

  return { items, total, page, perPage, filters, setFilter, setPage, loading, refetch };
}
