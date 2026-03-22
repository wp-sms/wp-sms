import { useState, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';
import { toast } from 'sonner';

export interface RegistrationFormField {
  id: string;
  required: boolean;
  sort_order: number;
}

export interface RegistrationFormData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  status: string;
  fields: RegistrationFormField[];
  auth_overrides: Record<string, Record<string, boolean>>;
  user_role: string;
  redirect_url: string;
  branding: Record<string, string>;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

interface ListResponse {
  items: RegistrationFormData[];
  total: number;
}

interface MutationResponse {
  success: boolean;
  data: RegistrationFormData;
  error?: string;
  message?: string;
}

export function useRegistrationForms() {
  const [forms, setForms] = useState<RegistrationFormData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<ListResponse>('/auth/admin/registration-forms');
      setForms(res.items);
    } catch (err: any) {
      setError(err.message || 'Failed to load registration forms');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchAll();
  }, [fetchAll]);

  const create = useCallback(async (data: Partial<RegistrationFormData>) => {
    const res = await api.post<MutationResponse>('/auth/admin/registration-forms', data);
    if (res.success) {
      setForms((prev) => [...prev, res.data]);
      toast.success('Registration form created');
    }
    return res;
  }, []);

  const update = useCallback(async (id: string, data: Partial<RegistrationFormData>) => {
    const res = await api.put<MutationResponse>(`/auth/admin/registration-forms/${id}`, data);
    if (res.success) {
      setForms((prev) => prev.map((f) => (f.id === id ? res.data : f)));
      toast.success('Registration form updated');
    }
    return res;
  }, []);

  const remove = useCallback(async (id: string) => {
    await api.del(`/auth/admin/registration-forms/${id}`);
    setForms((prev) => prev.filter((f) => f.id !== id));
    toast.success('Registration form deleted');
  }, []);

  const duplicate = useCallback(async (id: string) => {
    const res = await api.post<MutationResponse>(`/auth/admin/registration-forms/${id}/duplicate`, {});
    if (res.success) {
      setForms((prev) => [...prev, res.data]);
      toast.success('Registration form duplicated');
    }
    return res;
  }, []);

  return { forms, loading, error, fetchAll, create, update, remove, duplicate };
}
