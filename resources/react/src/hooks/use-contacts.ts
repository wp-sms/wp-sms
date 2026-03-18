import { useState, useEffect, useCallback, useRef } from 'react';
import { api, type Contact, type ContactDetail, type ImportResult, type ImportPreview, type ListResponse } from '@/lib/api';

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
  // Detail
  fetchContact: (id: string) => Promise<ContactDetail>;
  // Import/Export
  importPreview: (file: File) => Promise<ImportPreview>;
  importContacts: (file: File, options: { mapping: Record<string, string>; matchField: string; duplicateHandling: string }) => Promise<ImportResult>;
  exportContacts: (filters?: { status?: string }) => Promise<{ url: string; filename: string }>;
  // Bulk
  bulkAction: (action: string, ids: string[], params?: Record<string, unknown>) => Promise<void>;
  // Selection
  selectedIds: string[];
  toggleSelect: (id: string) => void;
  selectAll: () => void;
  clearSelection: () => void;
  isAllSelected: boolean;
}

const EMPTY_FILTERS: ContactFilters = { status: '', search: '' };

export function useContacts(perPage = 20): UseContactsReturn {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<ContactFilters>(EMPTY_FILTERS);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
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

  const fetchContact = useCallback(async (id: string): Promise<ContactDetail> => {
    const res = await api.get<{ success: boolean; data: ContactDetail }>(`contacts/${id}`);
    return res.data;
  }, []);

  const importPreview = useCallback(async (file: File): Promise<ImportPreview> => {
    const formData = new FormData();
    formData.append('file', file);
    const res = await api.upload<{ success: boolean; data: ImportPreview }>('contacts/import/preview', formData);
    return res.data;
  }, []);

  const importContacts = useCallback(async (
    file: File,
    { mapping, matchField, duplicateHandling }: { mapping: Record<string, string>; matchField: string; duplicateHandling: string },
  ): Promise<ImportResult> => {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('field_mapping', JSON.stringify(mapping));
    formData.append('match_field', matchField);
    formData.append('duplicate_handling', duplicateHandling);
    const res = await api.upload<{ success: boolean; data: ImportResult }>('contacts/import', formData);
    refetch();
    return res.data;
  }, [refetch]);

  const exportContacts = useCallback(async (exportFilters?: { status?: string }) => {
    const res = await api.post<{ success: boolean; data: { url: string; filename: string } }>('contacts/export', exportFilters ?? {});
    return res.data;
  }, []);

  const bulkAction = useCallback(async (action: string, ids: string[], params?: Record<string, unknown>): Promise<void> => {
    await api.post('contacts/bulk', { action, ids, ...params });
    setSelectedIds([]);
    refetch();
  }, [refetch]);

  // Selection
  const toggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
  }, []);

  const selectAll = useCallback(() => {
    setSelectedIds(contacts.map((c) => c.id));
  }, [contacts]);

  const clearSelection = useCallback(() => {
    setSelectedIds([]);
  }, []);

  const isAllSelected = contacts.length > 0 && selectedIds.length === contacts.length;

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

  // Clear selection on page/filter change
  useEffect(() => {
    setSelectedIds([]);
  }, [page, filters]);

  return {
    contacts, total, page, perPage, filters, setFilter, setPage, loading,
    createContact, updateContact, deleteContact, addTag, removeTag, refetch,
    fetchContact, importPreview, importContacts, exportContacts,
    bulkAction, selectedIds, toggleSelect, selectAll, clearSelection, isAllSelected,
  };
}
