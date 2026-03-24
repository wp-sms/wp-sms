import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type PlatformIntegration, type IntegrationDetail, type ListResponse } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

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
      if (isAbortError(e)) return;
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
  refetch: () => void;
} {
  const [detail, setDetail] = useState<IntegrationDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const abortRef = useRef<AbortController>();
  const idRef = useRef(id);
  idRef.current = id;

  const fetchDetail = useCallback(async () => {
    const currentId = idRef.current;
    if (!currentId) {
      setDetail(null);
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const res = await api.get<IntegrationDetail>(`integrations/${currentId}`, { signal: controller.signal });
      if (!controller.signal.aborted) setDetail(res);
    } catch (e) {
      if (isAbortError(e)) return;
      setDetail(null);
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, []);

  useEffect(() => {
    void fetchDetail();
    return () => { abortRef.current?.abort(); };
  }, [id, fetchDetail]);

  return { detail, loading, refetch: fetchDetail };
}

export function useIntegrationConfig(onSuccess?: () => void): {
  saveConfig: (id: string, credentials: Record<string, unknown>) => Promise<void>;
  disconnect: (id: string) => Promise<void>;
} {
  const saveConfig = useCallback(async (id: string, credentials: Record<string, unknown>) => {
    const res = await api.put<{ success: boolean; message?: string }>(`integrations/${id}/config`, { credentials });
    if (res.success === false) {
      throw new Error(res.message ?? 'Connection failed');
    }
    onSuccess?.();
  }, [onSuccess]);

  const disconnect = useCallback(async (id: string) => {
    await api.del(`integrations/${id}/config`);
    onSuccess?.();
  }, [onSuccess]);

  return { saveConfig, disconnect };
}

/**
 * Fetches availability status for a fixed set of integration IDs.
 * Uses individual detail endpoints instead of loading the full integrations list.
 */
export function useIntegrationAvailability(ids: readonly string[]): {
  availabilityMap: Map<string, boolean>;
  loading: boolean;
} {
  const [availabilityMap, setAvailabilityMap] = useState<Map<string, boolean>>(new Map());
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();

    async function fetchAll() {
      setLoading(true);
      const map = new Map<string, boolean>();

      const results = await Promise.allSettled(
        ids.map((id) =>
          api.get<IntegrationDetail>(`integrations/${id}`, { signal: controller.signal })
        ),
      );

      for (let i = 0; i < ids.length; i++) {
        const result = results[i];
        if (result.status === 'fulfilled') {
          map.set(ids[i], result.value.available);
        }
      }

      if (!controller.signal.aborted) {
        setAvailabilityMap(map);
        setLoading(false);
      }
    }

    void fetchAll();
    return () => { controller.abort(); };
  }, [ids]);

  return { availabilityMap, loading };
}
