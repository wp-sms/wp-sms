import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type ContactSource, type ContactSourceConfig, type ContactSourceFields, type ContactSourceStatus } from '@/lib/api';

export interface UseContactSourcesReturn {
  sources: ContactSource[];
  loading: boolean;
  refetch: () => void;
  connect: (type: string, config: Partial<ContactSourceConfig>) => Promise<void>;
  update: (type: string, config: ContactSourceConfig) => Promise<void>;
  disconnect: (type: string) => Promise<void>;
  startSync: (type: string) => Promise<{ total_available: number }>;
  getStatus: (type: string) => Promise<ContactSourceStatus>;
  getFields: (type: string) => Promise<ContactSourceFields>;
}

export function useContactSources(): UseContactSourcesReturn {
  const [sources, setSources] = useState<ContactSource[]>([]);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const abortRef = useRef<AbortController>();

  const refetch = useCallback(() => setFetchTrigger((n) => n + 1), []);

  useEffect(() => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    api.get<{ items: ContactSource[] }>('contact-sources', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) setSources(res.items);
      })
      .catch((e) => {
        if (e instanceof DOMException && e.name === 'AbortError') return;
        if (!controller.signal.aborted) setSources([]);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [fetchTrigger]);

  const connect = useCallback(async (type: string, config: Partial<ContactSourceConfig>) => {
    await api.post('contact-sources', { type, config });
    refetch();
  }, [refetch]);

  const update = useCallback(async (type: string, config: ContactSourceConfig) => {
    await api.put(`contact-sources/${type}`, { config });
    refetch();
  }, [refetch]);

  const disconnect = useCallback(async (type: string) => {
    await api.del(`contact-sources/${type}`);
    refetch();
  }, [refetch]);

  const startSync = useCallback(async (type: string) => {
    const res = await api.post<{ success: boolean; total_available: number }>(`contact-sources/${type}/sync`, {});
    refetch();
    return { total_available: res.total_available };
  }, [refetch]);

  const getStatus = useCallback(async (type: string) => {
    return api.get<ContactSourceStatus>(`contact-sources/${type}/status`);
  }, []);

  const getFields = useCallback(async (type: string) => {
    return api.get<ContactSourceFields>(`contact-sources/fields/${type}`);
  }, []);

  return {
    sources, loading, refetch,
    connect, update, disconnect,
    startSync, getStatus, getFields,
  };
}
