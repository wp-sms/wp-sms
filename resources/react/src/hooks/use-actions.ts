import { type ActionDefinition } from '@/lib/api';
import { createCachedResourceHook } from './use-cached-resource';

const { useCachedItems, getCachedItems: getCachedActions } =
  createCachedResourceHook<ActionDefinition>('actions');

export function useActions(): { actions: ActionDefinition[]; loading: boolean } {
  const { items, loading } = useCachedItems();
  return { actions: items, loading };
}

export { getCachedActions };
