import { useState, useEffect } from 'react';
import { api, type ActionDefinition, type ListResponse } from '@/lib/api';

let cachedActions: ActionDefinition[] | null = null;
let actionsFetch: Promise<ActionDefinition[]> | null = null;

/** Return the module-level cached actions (available after first mount). */
export function getCachedActions(): ActionDefinition[] {
  return cachedActions ?? [];
}

export function useActions(): { actions: ActionDefinition[]; loading: boolean } {
  const [actions, setActions] = useState<ActionDefinition[]>(cachedActions ?? []);
  const [loading, setLoading] = useState(!cachedActions);

  useEffect(() => {
    if (cachedActions) return;

    const controller = new AbortController();

    if (!actionsFetch) {
      actionsFetch = api.get<ListResponse<ActionDefinition>>('actions', { signal: controller.signal })
        .then((res) => { cachedActions = res.items; return res.items; })
        .catch((e) => { actionsFetch = null; throw e; });
    }

    actionsFetch
      .then((items) => { if (!controller.signal.aborted) { setActions(items); setLoading(false); } })
      .catch((e) => { if (!(e instanceof DOMException && e.name === 'AbortError')) setLoading(false); });

    return () => { controller.abort(); };
  }, []);

  return { actions, loading };
}
