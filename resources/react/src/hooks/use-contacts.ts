import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Contact, type ListResponse } from '@/lib/api';

export interface ContactFilters {
  status: string;
  search: string;
}

export interface UseContactsReturn {
  contacts: Contact[];
  total: number;
  page: number;
  perPage: number;
  filters: ContactFilters;
  setFilter: (key: keyof ContactFilters, value: string) => void;
  setPage: (page: number) => void;
  loading: boolean;
  createContact: (data: Partial<Contact>) => Promise<Contact>;
  updateContact: (id: string, data: Partial<Contact>) => Promise<Contact>;
  deleteContact: (id: string) => Promise<void>;
  addTag: (id: string, tagId: string) => Promise<void>;
  removeTag: (id: string, tagId: string) => Promise<void>;
  refetch: () => void;
}

const EMPTY_FILTERS: ContactFilters = { status: '', search: '' };

export function useContacts(perPage = 20): UseContactsReturn {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<ContactFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  const fetchContacts = useCallback(async (p: number, f: ContactFilters) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('per_page', String(perPage));
      params.set('offset', String((p - 1) * perPage));
      if (f.status) params.set('status', f.status);
      if (f.search) params.set('search', f.search);

      const res = await api.get<ListResponse<Contact>>(`contacts?${params.toString()}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setContacts(res.items);
        setTotal(res.total);
      }
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setContacts([]);
      setTotal(0);
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, [perPage]);

  const setFilter = useCallback((key: keyof ContactFilters, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
    setPage(1);
  }, []);

  const refetch = useCallback(() => {
    setFetchTrigger((n) => n + 1);
  }, []);

  const createContact = useCallback(async (data: Partial<Contact>): Promise<Contact> => {
    const res = await api.post<{ success: boolean; data: Contact }>('contacts', data);
    refetch();
    return res.data;
  }, [refetch]);

  const updateContact = useCallback(async (id: string, data: Partial<Contact>): Promise<Contact> => {
    const res = await api.put<{ success: boolean; data: Contact }>(`contacts/${id}`, data);
    refetch();
    return res.data;
  }, [refetch]);

  const deleteContact = useCallback(async (id: string): Promise<void> => {
    await api.del(`contacts/${id}`);
    refetch();
  }, [refetch]);

  const addTag = useCallback(async (id: string, tagId: string): Promise<void> => {
    await api.post(`contacts/${id}/tags`, { tag_id: tagId });
    refetch();
  }, [refetch]);

  const removeTag = useCallback(async (id: string, tagId: string): Promise<void> => {
    await api.del(`contacts/${id}/tags?tag_id=${tagId}`);
    refetch();
  }, [refetch]);

  useEffect(() => {
    clearTimeout(debounceRef.current);
    const hasFilterValue = filters.status || filters.search;
    debounceRef.current = setTimeout(() => {
      void fetchContacts(page, filters);
    }, page === 1 && hasFilterValue ? 300 : 0);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [page, filters, fetchContacts, fetchTrigger]);

  return { contacts, total, page, perPage, filters, setFilter, setPage, loading, createContact, updateContact, deleteContact, addTag, removeTag, refetch };
}
