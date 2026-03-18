import { useState, useEffect, useCallback } from 'react';
import { api, type ContactList, type ListResponse } from '@/lib/api';

export interface UseListsReturn {
  lists: ContactList[];
  loading: boolean;
  createList: (data: Partial<ContactList>) => Promise<ContactList>;
  updateList: (id: string, data: Partial<ContactList>) => Promise<ContactList>;
  deleteList: (id: string) => Promise<void>;
  refetch: () => void;
}

export function useLists(type?: string): UseListsReturn {
  const [lists, setLists] = useState<ContactList[]>([]);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);

  const refetch = useCallback(() => setFetchTrigger((n) => n + 1), []);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);

    const params = new URLSearchParams();
    if (type) params.set('type', type);

    api.get<{ items: ContactList[] }>(`lists?${params.toString()}`, { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) setLists(res.items);
      })
      .catch((e) => {
        if (!(e instanceof DOMException && e.name === 'AbortError')) setLists([]);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [type, fetchTrigger]);

  const createList = useCallback(async (data: Partial<ContactList>): Promise<ContactList> => {
    const res = await api.post<{ success: boolean; data: ContactList }>('lists', data);
    refetch();
    return res.data;
  }, [refetch]);

  const updateList = useCallback(async (id: string, data: Partial<ContactList>): Promise<ContactList> => {
    const res = await api.put<{ success: boolean; data: ContactList }>(`lists/${id}`, data);
    refetch();
    return res.data;
  }, [refetch]);

  const deleteList = useCallback(async (id: string): Promise<void> => {
    await api.del(`lists/${id}`);
    refetch();
  }, [refetch]);

  return { lists, loading, createList, updateList, deleteList, refetch };
}
