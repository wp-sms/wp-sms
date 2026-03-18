import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Campaign, type CampaignStatus } from '@/lib/api';

export interface CampaignFilters {
  status: CampaignStatus | '';
  channel: string;
}

export interface UseCampaignsReturn {
  campaigns: Campaign[];
  total: number;
  page: number;
  perPage: number;
  filters: CampaignFilters;
  setFilter: (key: keyof CampaignFilters, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
  createCampaign: (data: Partial<Campaign>) => Promise<Campaign>;
  updateCampaign: (id: string, data: Partial<Campaign>) => Promise<Campaign>;
  deleteCampaign: (id: string) => Promise<void>;
  duplicateCampaign: (id: string) => Promise<Campaign>;
  sendCampaign: (id: string) => Promise<Campaign>;
  scheduleCampaign: (id: string, sendAt: string, timezone: string) => Promise<Campaign>;
  cancelCampaign: (id: string) => Promise<Campaign>;
  pauseCampaign: (id: string) => Promise<Campaign>;
  resumeCampaign: (id: string) => Promise<Campaign>;
  refetch: () => void;
}

const EMPTY_FILTERS: CampaignFilters = { status: '', channel: '' };

export function useCampaigns(perPage = 20): UseCampaignsReturn {
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<CampaignFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  const fetchCampaigns = useCallback(async (p: number, f: CampaignFilters) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('per_page', String(perPage));
      params.set('page', String(p));
      if (f.status) params.set('status', f.status);
      if (f.channel) params.set('channel', f.channel);

      const res = await api.get<{ items: Campaign[]; total: number }>(`campaigns?${params.toString()}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setCampaigns(res.items);
        setTotal(res.total);
      }
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setCampaigns([]);
      setTotal(0);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, [perPage]);

  const setFilter = useCallback((key: keyof CampaignFilters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  }, []);

  const refetch = useCallback(() => {
    setFetchTrigger((n) => n + 1);
  }, []);

  const createCampaign = useCallback(async (data: Partial<Campaign>): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>('campaigns', data);
    refetch();
    return res.data;
  }, [refetch]);

  const updateCampaign = useCallback(async (id: string, data: Partial<Campaign>): Promise<Campaign> => {
    const res = await api.put<{ success: boolean; data: Campaign }>(`campaigns/${id}`, data);
    refetch();
    return res.data;
  }, [refetch]);

  const deleteCampaign = useCallback(async (id: string): Promise<void> => {
    await api.del(`campaigns/${id}`);
    refetch();
  }, [refetch]);

  const duplicateCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/duplicate`, {});
    refetch();
    return res.data;
  }, [refetch]);

  const sendCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/send`, {});
    refetch();
    return res.data;
  }, [refetch]);

  const scheduleCampaign = useCallback(async (id: string, sendAt: string, timezone: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/schedule`, { send_at: sendAt, timezone });
    refetch();
    return res.data;
  }, [refetch]);

  const cancelCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/cancel`, {});
    refetch();
    return res.data;
  }, [refetch]);

  const pauseCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/pause`, {});
    refetch();
    return res.data;
  }, [refetch]);

  const resumeCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/resume`, {});
    refetch();
    return res.data;
  }, [refetch]);

  useEffect(() => {
    clearTimeout(debounceRef.current);
    const hasFilterValue = filters.status || filters.channel;
    debounceRef.current = setTimeout(() => {
      void fetchCampaigns(page, filters);
    }, page === 1 && hasFilterValue ? 300 : 0);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [page, filters, fetchCampaigns, fetchTrigger]);

  return {
    campaigns, total, page, perPage, filters, setFilter, setPage, loading,
    createCampaign, updateCampaign, deleteCampaign, duplicateCampaign,
    sendCampaign, scheduleCampaign, cancelCampaign, pauseCampaign, resumeCampaign,
    refetch,
  };
}
