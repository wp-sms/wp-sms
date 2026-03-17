import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Gateway, type ListResponse, type GatewayTestResult } from '@/lib/api';

export interface UseGatewaysReturn {
  gateways: Gateway[];
  loading: boolean;
  updateConfig: (config: Record<string, Record<string, unknown>>) => Promise<void>;
  testGateway: (id: string, data: { channel?: string; to: string; body?: string }) => Promise<GatewayTestResult>;
  refetch: () => void;
}

export function useGateways(): UseGatewaysReturn {
  const [gateways, setGateways] = useState<Gateway[]>([]);
  const [loading, setLoading] = useState(true);
  const abortRef = useRef<AbortController>();

  const fetchGateways = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const res = await api.get<ListResponse<Gateway>>('wsms/v1/gateways', { signal: controller.signal });
      if (!controller.signal.aborted) {
        setGateways(res.items);
      }
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setGateways([]);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  const updateConfig = useCallback(async (config: Record<string, Record<string, unknown>>): Promise<void> => {
    await api.put('wsms/v1/gateways/config', config);
    void fetchGateways();
  }, [fetchGateways]);

  const testGateway = useCallback(async (id: string, data: { channel?: string; to: string; body?: string }): Promise<GatewayTestResult> => {
    return api.post<GatewayTestResult>(`wsms/v1/gateways/${id}/test`, data);
  }, []);

  useEffect(() => {
    void fetchGateways();
    return () => { abortRef.current?.abort(); };
  }, [fetchGateways]);

  return { gateways, loading, updateConfig, testGateway, refetch: fetchGateways };
}
