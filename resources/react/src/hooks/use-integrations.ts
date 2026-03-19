import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type PlatformIntegration, type IntegrationDetail, type ListResponse } from '@/lib/api';

export function useIntegrations(): {
  integrations: PlatformIntegration[];
  loading: boolean;
  refetch: () => void;
} {
  const [integrations, setIntegrations] = useState<PlatformIntegration[]>([]);
  const [loading, setLoading] = useState(true);
  const abortRef = useRef<AbortController>();

  const fetchIntegrations = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const res = await api.get<ListResponse<PlatformIntegration>>('integrations', { signal: controller.signal });
      if (!controller.signal.aborted) {
        setIntegrations(res.items);
      }
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setIntegrations([]);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    void fetchIntegrations();
    return () => { abortRef.current?.abort(); };
  }, [fetchIntegrations]);

  return { integrations, loading, refetch: fetchIntegrations };
}

export function useIntegrationDetail(id: string | null): {
  detail: IntegrationDetail | null;
  loading: boolean;
} {
  const [detail, setDetail] = useState<IntegrationDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const abortRef = useRef<AbortController>();

  useEffect(() => {
    if (!id) {
      setDetail(null);
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    async function fetch() {
      setLoading(true);
      try {
        const res = await api.get<IntegrationDetail>(`integrations/${id}`, { signal: controller.signal });
        if (!controller.signal.aborted) setDetail(res);
      } catch (e) {
        if (e instanceof DOMException && e.name === 'AbortError') return;
        setDetail(null);
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    void fetch();
    return () => { controller.abort(); };
  }, [id]);

  return { detail, loading };
}

export function useIntegrationConfig(onSuccess?: () => void): {
  saveConfig: (id: string, credentials: Record<string, unknown>) => Promise<void>;
  disconnect: (id: string) => Promise<void>;
} {
  const saveConfig = useCallback(async (id: string, credentials: Record<string, unknown>) => {
    await api.put(`integrations/${id}/config`, { credentials });
    onSuccess?.();
  }, [onSuccess]);

  const disconnect = useCallback(async (id: string) => {
    await api.del(`integrations/${id}/config`);
    onSuccess?.();
  }, [onSuccess]);

  return { saveConfig, disconnect };
}
