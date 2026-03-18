import { useState, useCallback, useRef, useEffect } from 'react';
import { api, type Contact, type SegmentConditionGroup } from '@/lib/api';

export interface UseSegmentPreviewReturn {
  count: number;
  contacts: Contact[];
  loading: boolean;
  evaluate: (conditions: SegmentConditionGroup) => void;
}

export function useSegmentPreview(): UseSegmentPreviewReturn {
  const [count, setCount] = useState(0);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  const evaluate = useCallback((conditions: SegmentConditionGroup) => {
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(async () => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setLoading(true);
      try {
        const res = await api.post<{ items: Contact[]; total: number }>(
          'segments/preview',
          { conditions },
          { signal: controller.signal },
        );
        if (!controller.signal.aborted) {
          setContacts(res.items);
          setCount(res.total);
        }
      } catch (e) {
        if (!(e instanceof DOMException && e.name === 'AbortError')) {
          setContacts([]);
          setCount(0);
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }, 500);
  }, []);

  useEffect(() => {
    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, []);

  return { count, contacts, loading, evaluate };
}
