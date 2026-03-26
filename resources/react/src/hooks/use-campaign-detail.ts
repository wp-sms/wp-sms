import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Campaign, type CampaignStats, type MessageLogEntry } from '@/lib/api';

export interface UseCampaignDetailReturn {
  campaign: Campaign | null;
  stats: CampaignStats | null;
  recipients: MessageLogEntry[];
  recipientsTotal: number;
  recipientsPage: number;
  setRecipientsPage: (page: number) => void;
  statusFilter: string;
  setStatusFilter: (status: string) => void;
  recipientSearch: string;
  setRecipientSearch: (search: string) => void;
  loading: boolean;
  refetch: () => void;
}

export function useCampaignDetail(campaignId: string, autoRefresh = false): UseCampaignDetailReturn {
  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [stats, setStats] = useState<CampaignStats | null>(null);
  const [recipients, setRecipients] = useState<MessageLogEntry[]>([]);
  const [recipientsTotal, setRecipientsTotal] = useState(0);
  const [recipientsPage, setRecipientsPage] = useState(1);
  const [statusFilter, setStatusFilterRaw] = useState('');
  const [recipientSearch, setRecipientSearchRaw] = useState('');
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const intervalRef = useRef<ReturnType<typeof setInterval>>();

  const setStatusFilter = useCallback((status: string) => {
    setStatusFilterRaw(status);
    setRecipientsPage(1);
  }, []);

  const setRecipientSearch = useCallback((search: string) => {
    setRecipientSearchRaw(search);
    setRecipientsPage(1);
  }, []);

  const fetchData = useCallback(async () => {
    try {
      const recipientParams = new URLSearchParams({
        per_page: '50',
        page: String(recipientsPage),
      });
      if (statusFilter) recipientParams.set('status', statusFilter);
      if (recipientSearch) recipientParams.set('recipient', recipientSearch);

      const [campaignRes, statsRes, recipientsRes] = await Promise.all([
        api.get<{ success: boolean; data: Campaign }>(`campaigns/${campaignId}`),
        api.get<{ success: boolean; data: CampaignStats }>(`campaigns/${campaignId}/stats`),
        api.get<{ items: MessageLogEntry[]; total: number }>(
          `campaigns/${campaignId}/recipients?${recipientParams.toString()}`,
        ),
      ]);

      setCampaign(campaignRes.data);
      setStats(statsRes.data);
      setRecipients(recipientsRes.items);
      setRecipientsTotal(recipientsRes.total);
    } catch {
      // Keep existing data on refresh failures
    } finally {
      setLoading(false);
    }
  }, [campaignId, recipientsPage, statusFilter, recipientSearch]);

  const refetch = useCallback(() => {
    setFetchTrigger((n) => n + 1);
  }, []);

  useEffect(() => {
    setLoading(true);
    void fetchData();
  }, [fetchData, fetchTrigger]);

  // Auto-refresh every 5s when sending
  useEffect(() => {
    if (autoRefresh && campaign?.status === 'sending') {
      intervalRef.current = setInterval(() => {
        void fetchData();
      }, 5000);
    }
    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, [autoRefresh, campaign?.status, fetchData]);

  return {
    campaign, stats, recipients, recipientsTotal,
    recipientsPage, setRecipientsPage,
    statusFilter, setStatusFilter,
    recipientSearch, setRecipientSearch,
    loading, refetch,
  };
}
