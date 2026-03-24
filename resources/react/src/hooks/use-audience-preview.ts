import { useState, useEffect, useRef, useMemo } from 'react';
import { api, type CampaignAudience } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

export function useAudiencePreview(audience: CampaignAudience | null, channel: string) {
  const [count, setCount] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const abortRef = useRef<AbortController>();

  // Stabilize audience reference — only change when content actually changes
  const audienceKey = useMemo(
    () => (audience ? JSON.stringify(audience) : ''),
    [audience],
  );

  useEffect(() => {
    if (!audienceKey || !channel) {
      setCount(null);
      return;
    }

    const parsed: CampaignAudience = JSON.parse(audienceKey);
    if (!parsed.sources?.length) {
      setCount(null);
      return;
    }

    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(async () => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setLoading(true);
      try {
        const res = await api.post<{ success: boolean; data: { count: number } }>(
          'campaigns/audience-count',
          { audience: parsed, channel },
          { signal: controller.signal },
        );
        if (!controller.signal.aborted) {
          setCount(res.data.count);
        }
      } catch (e) {
        if (isAbortError(e)) return;
        setCount(null);
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }, 500);

    return () => {
      clearTimeout(debounceRef.current);
      abortRef.current?.abort();
    };
  }, [audienceKey, channel]);

  return { count, loading };
}
