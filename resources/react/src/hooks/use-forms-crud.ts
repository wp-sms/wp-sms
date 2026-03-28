import { useState, useEffect, useCallback } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { api, type ListResponse, type MutationResponse } from '@/lib/api';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/error-utils';

interface FormsCrudConfig {
  endpoint: string;
  label: string;
}

export function useFormsCrud<T extends { id: string }>(config: FormsCrudConfig) {
  const { endpoint, label } = config;
  const [forms, setForms] = useState<T[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<ListResponse<T>>(endpoint);
      setForms(res.items);
    } catch (err: unknown) {
      setError(getErrorMessage(err, __('Failed to load items.', 'wp-sms')));
    } finally {
      setLoading(false);
    }
  }, [endpoint, label]);

  useEffect(() => {
    fetchAll();
  }, [fetchAll]);

  const create = useCallback(async (data: Partial<T>) => {
    const res = await api.post<MutationResponse<T>>(endpoint, data);
    if (res.success) {
      setForms((prev) => [...prev, res.data]);
      toast.success(sprintf(__('%s created', 'wp-sms'), label));
    }
    return res;
  }, [endpoint, label]);

  const update = useCallback(async (id: string, data: Partial<T>) => {
    const res = await api.put<MutationResponse<T>>(`${endpoint}/${id}`, data);
    if (res.success) {
      setForms((prev) => prev.map((f) => (f.id === id ? res.data : f)));
      toast.success(sprintf(__('%s updated', 'wp-sms'), label));
    }
    return res;
  }, [endpoint, label]);

  const remove = useCallback(async (id: string) => {
    try {
      await api.del(`${endpoint}/${id}`);
      setForms((prev) => prev.filter((f) => f.id !== id));
      toast.success(sprintf(__('%s deleted', 'wp-sms'), label));
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, sprintf(__('Failed to delete %s', 'wp-sms'), label.toLowerCase())));
    }
  }, [endpoint, label]);

  const duplicate = useCallback(async (id: string) => {
    const res = await api.post<MutationResponse<T>>(`${endpoint}/${id}/duplicate`, {});
    if (res.success) {
      setForms((prev) => [...prev, res.data]);
      toast.success(sprintf(__('%s duplicated', 'wp-sms'), label));
    }
    return res;
  }, [endpoint, label]);

  return { forms, loading, error, fetchAll, create, update, remove, duplicate };
}
