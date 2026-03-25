import { useEffect } from 'react';

/**
 * Triggers a callback when the parent increments a counter prop.
 * Used for parent-to-child action signaling (e.g., opening a create form from a PageHeader button).
 */
export function useCreateTrigger(trigger: number | undefined, onTrigger: () => void) {
  useEffect(() => {
    if (trigger && trigger > 0) onTrigger();
  }, [trigger, onTrigger]);
}
