import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Gateway, type ListResponse, type GatewayTestResult, type TestConnectionResult } from '@/lib/api';

export interface UseGatewaysReturn {
  gateways: Gateway[];
  loading: boolean;
  updateConfig: (config: Record<string, Record<string, unknown>>) => Promise<void>;
  testGateway: (id: string, data: { channel?: string; to: string; body?: string }) => Promise<GatewayTestResult>;
  testConnection: (id: string) => Promise<TestConnectionResult>;
  getCredit: (id: string) => Promise<string | null>;
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
      const res = await api.get<ListResponse<Gateway>>('gateways', { signal: controller.signal });
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
    await api.put('gateways/config', config);
    void fetchGateways();
  }, [fetchGateways]);

  const testGateway = useCallback(async (id: string, data: { channel?: string; to: string; body?: string }): Promise<GatewayTestResult> => {
    return api.post<GatewayTestResult>(`gateways/${id}/test`, data);
  }, []);

  const testConnection = useCallback(async (id: string): Promise<TestConnectionResult> => {
    return api.post<TestConnectionResult>(`gateways/${id}/test-connection`, null);
  }, []);

  const getCredit = useCallback(async (id: string): Promise<string | null> => {
    try {
      const res = await api.get<{ credit: string | null }>(`gateways/${id}/credit`);
      return res.credit;
    } catch {
      return null;
    }
  }, []);

  useEffect(() => {
    void fetchGateways();
    return () => { abortRef.current?.abort(); };
  }, [fetchGateways]);

  return { gateways, loading, updateConfig, testGateway, testConnection, getCredit, refetch: fetchGateways };
}
