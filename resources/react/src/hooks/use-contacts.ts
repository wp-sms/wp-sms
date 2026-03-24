import { useState, useEffect, useCallback } from 'react';
import { api, type Contact, type ContactDetail, type ImportResult, type ImportPreview } from '@/lib/api';
import { useListResource } from './use-list-resource';

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

export function useContacts(perPage = 20): UseContactsReturn {
  const list = useListResource<Contact, ContactFilters>({
    endpoint: 'contacts',
    defaultFilters: { status: '', search: '' },
    perPage,
  });

  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  const createContact = useCallback(async (data: Partial<Contact>): Promise<Contact> => {
    const res = await api.post<{ success: boolean; data: Contact }>('contacts', data);
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const updateContact = useCallback(async (id: string, data: Partial<Contact>): Promise<Contact> => {
    const res = await api.put<{ success: boolean; data: Contact }>(`contacts/${id}`, data);
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const deleteContact = useCallback(async (id: string): Promise<void> => {
    await api.del(`contacts/${id}`);
    list.refetch();
  }, [list.refetch]);

  const addTag = useCallback(async (id: string, tagId: string): Promise<void> => {
    await api.post(`contacts/${id}/tags`, { tag_id: tagId });
    list.refetch();
  }, [list.refetch]);

  const removeTag = useCallback(async (id: string, tagId: string): Promise<void> => {
    await api.del(`contacts/${id}/tags?tag_id=${tagId}`);
    list.refetch();
  }, [list.refetch]);

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
    list.refetch();
    return res.data;
  }, [list.refetch]);

  const exportContacts = useCallback(async (exportFilters?: { status?: string }) => {
    const res = await api.post<{ success: boolean; data: { url: string; filename: string } }>('contacts/export', exportFilters ?? {});
    return res.data;
  }, []);

  const bulkAction = useCallback(async (action: string, ids: string[], params?: Record<string, unknown>): Promise<void> => {
    await api.post('contacts/bulk', { action, ids, ...params });
    setSelectedIds([]);
    list.refetch();
  }, [list.refetch]);

  // Selection
  const toggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
  }, []);

  const selectAll = useCallback(() => {
    setSelectedIds(list.items.map((c) => c.id));
  }, [list.items]);

  const clearSelection = useCallback(() => {
    setSelectedIds([]);
  }, []);

  const isAllSelected = list.items.length > 0 && selectedIds.length === list.items.length;

  // Clear selection on page/filter change
  useEffect(() => {
    setSelectedIds([]);
  }, [list.page, list.filters]);

  return {
    contacts: list.items, total: list.total, page: list.page, perPage: list.perPage,
    filters: list.filters, setFilter: list.setFilter, setPage: list.setPage, loading: list.loading,
    createContact, updateContact, deleteContact, addTag, removeTag, refetch: list.refetch,
    fetchContact, importPreview, importContacts, exportContacts,
    bulkAction, selectedIds, toggleSelect, selectAll, clearSelection, isAllSelected,
  };
}
