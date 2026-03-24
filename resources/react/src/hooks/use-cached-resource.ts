import { useState, useEffect } from 'react';
import { api, type ListResponse } from '@/lib/api';

export function createCachedResourceHook<T>(endpoint: string) {
  let cached: T[] | null = null;
  let inflight: Promise<T[]> | null = null;

  function useCachedItems(): { items: T[]; loading: boolean } {
    const [items, setItems] = useState<T[]>(cached ?? []);
    const [loading, setLoading] = useState(!cached);

    useEffect(() => {
      if (cached) return;

      let cancelled = false;

      if (!inflight) {
        inflight = api.get<ListResponse<T>>(endpoint)
          .then((res) => { cached = res.items; return res.items; })
          .catch((e) => { inflight = null; throw e; });
      }

      inflight
        .then((data) => { if (!cancelled) { setItems(data); setLoading(false); } })
        .catch((e) => { if (!cancelled) setLoading(false); });

      return () => { cancelled = true; };
    }, []);

    return { items, loading };
  }

  function getCachedItems(): T[] {
    return cached ?? [];
  }

  return { useCachedItems, getCachedItems };
}
