import { type TriggerDefinition } from '@/lib/api';
import { createCachedResourceHook } from './use-cached-resource';

const { useCachedItems } = createCachedResourceHook<TriggerDefinition>('triggers');

export function useTriggers(): { triggers: TriggerDefinition[]; loading: boolean } {
  const { items, loading } = useCachedItems();
  return { triggers: items, loading };
}
