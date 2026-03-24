import { useState, useEffect, useCallback } from 'react';
import { api, type SubscriptionFormData } from '@/lib/api';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/error-utils';

export type { SubscriptionFormData };

interface ListResponse {
  items: SubscriptionFormData[];
  total: number;
}

interface MutationResponse {
  success: boolean;
  data: SubscriptionFormData;
  error?: string;
  message?: string;
}

export function useSubscriptionForms() {
  const [forms, setForms] = useState<SubscriptionFormData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<ListResponse>('/subscription-forms');
      setForms(res.items);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to load subscription forms'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchAll();
  }, [fetchAll]);

  const create = useCallback(async (data: Partial<SubscriptionFormData>) => {
    const res = await api.post<MutationResponse>('/subscription-forms', data);
    if (res.success) {
      setForms((prev) => [...prev, res.data]);
      toast.success('Subscription form created');
    }
    return res;
  }, []);

  const update = useCallback(async (id: string, data: Partial<SubscriptionFormData>) => {
    const res = await api.put<MutationResponse>(`/subscription-forms/${id}`, data);
    if (res.success) {
      setForms((prev) => prev.map((f) => (f.id === id ? res.data : f)));
      toast.success('Subscription form updated');
    }
    return res;
  }, []);

  const remove = useCallback(async (id: string) => {
    await api.del(`/subscription-forms/${id}`);
    setForms((prev) => prev.filter((f) => f.id !== id));
    toast.success('Subscription form deleted');
  }, []);

  const duplicate = useCallback(async (id: string) => {
    const res = await api.post<MutationResponse>(`/subscription-forms/${id}/duplicate`, {});
    if (res.success) {
      setForms((prev) => [...prev, res.data]);
      toast.success('Subscription form duplicated');
    }
    return res;
  }, []);

  return { forms, loading, error, fetchAll, create, update, remove, duplicate };
}
