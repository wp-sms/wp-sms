import { useCallback, useEffect, useState } from 'react';
import { api, type OutboundWebhook, type WebhookTestResult, type WebhookEventGroups } from '@/lib/api';

export interface UseWebhooksReturn {
  webhooks: OutboundWebhook[];
  loading: boolean;
  eventGroups: WebhookEventGroups | null;
  eventsLoading: boolean;
  createWebhook: (data: { name: string; url: string; events: string[]; description?: string }) => Promise<OutboundWebhook>;
  updateWebhook: (id: string, data: Partial<OutboundWebhook> & { regenerate_secret?: boolean }) => Promise<OutboundWebhook>;
  deleteWebhook: (id: string) => Promise<void>;
  toggleWebhook: (id: string) => Promise<OutboundWebhook>;
  testConnection: (id: string) => Promise<WebhookTestResult>;
  refetch: () => void;
}

export function useWebhooks(): UseWebhooksReturn {
  const [webhooks, setWebhooks] = useState<OutboundWebhook[]>([]);
  const [loading, setLoading] = useState(true);
  const [eventGroups, setEventGroups] = useState<WebhookEventGroups | null>(null);
  const [eventsLoading, setEventsLoading] = useState(true);
  const [fetchKey, setFetchKey] = useState(0);

  const refetch = useCallback(() => setFetchKey((k) => k + 1), []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);

    api.get<{ success: boolean; data: OutboundWebhook[] }>('outbound-webhooks')
      .then((res) => {
        if (!cancelled) setWebhooks(res.data);
      })
      .catch(() => {
        if (!cancelled) setWebhooks([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  }, [fetchKey]);

  useEffect(() => {
    let cancelled = false;
    setEventsLoading(true);

    api.get<{ success: boolean; data: WebhookEventGroups }>('outbound-webhooks/events')
      .then((res) => {
        if (!cancelled) setEventGroups(res.data);
      })
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setEventsLoading(false);
      });

    return () => { cancelled = true; };
  }, []);

  const createWebhook = useCallback(async (data: { name: string; url: string; events: string[]; description?: string }): Promise<OutboundWebhook> => {
    const res = await api.post<{ success: boolean; data: OutboundWebhook }>('outbound-webhooks', data);
    refetch();
    return res.data;
  }, [refetch]);

  const updateWebhook = useCallback(async (id: string, data: Partial<OutboundWebhook> & { regenerate_secret?: boolean }): Promise<OutboundWebhook> => {
    const res = await api.put<{ success: boolean; data: OutboundWebhook }>(`outbound-webhooks/${id}`, data);
    refetch();
    return res.data;
  }, [refetch]);

  const deleteWebhook = useCallback(async (id: string): Promise<void> => {
    await api.del(`outbound-webhooks/${id}`);
    refetch();
  }, [refetch]);

  const toggleWebhook = useCallback(async (id: string): Promise<OutboundWebhook> => {
    const res = await api.post<{ success: boolean; data: OutboundWebhook }>(`outbound-webhooks/${id}/toggle`, {});
    refetch();
    return res.data;
  }, [refetch]);

  const testConnection = useCallback(async (id: string): Promise<WebhookTestResult> => {
    const res = await api.post<WebhookTestResult>(`outbound-webhooks/${id}/test-connection`, {});
    refetch();
    return res;
  }, [refetch]);

  return {
    webhooks, loading, eventGroups, eventsLoading,
    createWebhook, updateWebhook, deleteWebhook, toggleWebhook, testConnection,
    refetch,
  };
}
