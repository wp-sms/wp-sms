import { useState, useEffect } from 'react';
import { api, type TriggerDefinition, type ListResponse } from '@/lib/api';

let cachedTriggers: TriggerDefinition[] | null = null;
let triggersFetch: Promise<TriggerDefinition[]> | null = null;

export function useTriggers(): { triggers: TriggerDefinition[]; loading: boolean } {
  const [triggers, setTriggers] = useState<TriggerDefinition[]>(cachedTriggers ?? []);
  const [loading, setLoading] = useState(!cachedTriggers);

  useEffect(() => {
    if (cachedTriggers) return;

    const controller = new AbortController();

    if (!triggersFetch) {
      triggersFetch = api.get<ListResponse<TriggerDefinition>>('triggers', { signal: controller.signal })
        .then((res) => { cachedTriggers = res.items; return res.items; })
        .catch((e) => { triggersFetch = null; throw e; });
    }

    triggersFetch
      .then((items) => { if (!controller.signal.aborted) { setTriggers(items); setLoading(false); } })
      .catch((e) => { if (!(e instanceof DOMException && e.name === 'AbortError')) setLoading(false); });

    return () => { controller.abort(); };
  }, []);

  return { triggers, loading };
}
