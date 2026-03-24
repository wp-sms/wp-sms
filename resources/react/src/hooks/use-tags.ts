import { useState, useEffect, useCallback } from 'react';
import { api, type Tag } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

export interface UseTagsReturn {
  tags: Tag[];
  loading: boolean;
  createTag: (data: { name: string; slug?: string; color?: string }) => Promise<Tag>;
  updateTag: (id: string, data: { name?: string; slug?: string; color?: string }) => Promise<Tag>;
  deleteTag: (id: string) => Promise<void>;
  refetch: () => void;
}

export function useTags(): UseTagsReturn {
  const [tags, setTags] = useState<Tag[]>([]);
  const [loading, setLoading] = useState(true);
  const [fetchTrigger, setFetchTrigger] = useState(0);

  const refetch = useCallback(() => setFetchTrigger((n) => n + 1), []);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);

    api.get<{ items: Tag[] }>('tags', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) setTags(res.items);
      })
      .catch((e) => {
        if (!isAbortError(e)) setTags([]);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [fetchTrigger]);

  const createTag = useCallback(async (data: { name: string; slug?: string; color?: string }): Promise<Tag> => {
    const res = await api.post<{ success: boolean; data: Tag }>('tags', data);
    refetch();
    return res.data;
  }, [refetch]);

  const updateTag = useCallback(async (id: string, data: { name?: string; slug?: string; color?: string }): Promise<Tag> => {
    const res = await api.put<{ success: boolean; data: Tag }>(`tags/${id}`, data);
    refetch();
    return res.data;
  }, [refetch]);

  const deleteTag = useCallback(async (id: string): Promise<void> => {
    await api.del(`tags/${id}`);
    refetch();
  }, [refetch]);

  return { tags, loading, createTag, updateTag, deleteTag, refetch };
}
