import { useCallback } from 'react';
import { api, type Campaign, type CampaignStatus } from '@/lib/api';
import { useListResource } from './use-list-resource';

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

export function useCampaigns(perPage = 20): UseCampaignsReturn {
  const list = useListResource<Campaign, CampaignFilters>({
    endpoint: 'campaigns',
    defaultFilters: { status: '', channel: '' },
    perPage,
    paginationMode: 'page',
  });

  const createCampaign = useCallback(async (data: Partial<Campaign>): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>('campaigns', data);
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const updateCampaign = useCallback(async (id: string, data: Partial<Campaign>): Promise<Campaign> => {
    const res = await api.put<{ success: boolean; data: Campaign }>(`campaigns/${id}`, data);
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const deleteCampaign = useCallback(async (id: string): Promise<void> => {
    await api.del(`campaigns/${id}`);
    list.refetch();
  }, [list.refetch]);

  const duplicateCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/duplicate`, {});
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const sendCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/send`, {});
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const scheduleCampaign = useCallback(async (id: string, sendAt: string, timezone: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/schedule`, { send_at: sendAt, timezone });
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const cancelCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/cancel`, {});
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const pauseCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/pause`, {});
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const resumeCampaign = useCallback(async (id: string): Promise<Campaign> => {
    const res = await api.post<{ success: boolean; data: Campaign }>(`campaigns/${id}/resume`, {});
    list.refetch();
    return res.data;
  }, [list.refetch]);

  return {
    campaigns: list.items, total: list.total, page: list.page, perPage: list.perPage,
    filters: list.filters, setFilter: list.setFilter, setPage: list.setPage, loading: list.loading,
    createCampaign, updateCampaign, deleteCampaign, duplicateCampaign,
    sendCampaign, scheduleCampaign, cancelCampaign, pauseCampaign, resumeCampaign,
    refetch: list.refetch,
  };
}
