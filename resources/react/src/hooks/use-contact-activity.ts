import { useState, useEffect, useCallback } from 'react';
import { api, type ContactActivity } from '@/lib/api';

export interface UseContactActivityReturn {
  activities: ContactActivity[];
  loading: boolean;
  loadMore: () => void;
  hasMore: boolean;
}

export function useContactActivity(contactId: string | null): UseContactActivityReturn {
  const [activities, setActivities] = useState<ContactActivity[]>([]);
  const [loading, setLoading] = useState(false);
  const [offset, setOffset] = useState(0);
  const [hasMore, setHasMore] = useState(true);
  const perPage = 20;

  useEffect(() => {
    if (!contactId) {
      setActivities([]);
      return;
    }

    const controller = new AbortController();
    setLoading(true);
    setOffset(0);

    api.get<{ items: ContactActivity[] }>(
      `contacts/${contactId}/activity?per_page=${perPage}&offset=0`,
      { signal: controller.signal },
    )
      .then((res) => {
        if (!controller.signal.aborted) {
          setActivities(res.items);
          setHasMore(res.items.length >= perPage);
        }
      })
      .catch((e) => {
        if (!(e instanceof DOMException && e.name === 'AbortError')) {
          setActivities([]);
          setHasMore(false);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [contactId]);

  const loadMore = useCallback(async () => {
    if (!contactId || loading) return;
    const newOffset = offset + perPage;
    setLoading(true);

    try {
      const res = await api.get<{ items: ContactActivity[] }>(
        `contacts/${contactId}/activity?per_page=${perPage}&offset=${newOffset}`,
      );
      setActivities((prev) => [...prev, ...res.items]);
      setOffset(newOffset);
      setHasMore(res.items.length >= perPage);
    } catch {
      setHasMore(false);
    } finally {
      setLoading(false);
    }
  }, [contactId, offset, loading]);

  return { activities, loading, loadMore, hasMore };
}
