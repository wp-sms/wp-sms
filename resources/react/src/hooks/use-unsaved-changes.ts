import { useEffect, useCallback } from 'react';
import { useConfirm } from '@/components/confirm-provider';

interface UseUnsavedChangesOptions {
  isDirty: () => boolean;
  onLeave: () => void;
}

export function useUnsavedChanges({ isDirty, onLeave }: UseUnsavedChangesOptions) {
  const confirm = useConfirm();

  // beforeunload for browser tab close / navigation
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (isDirty()) e.preventDefault();
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty]);

  // Guarded back button — shows AlertDialog if dirty
  const guardedBack = useCallback(async () => {
    if (!isDirty()) {
      onLeave();
      return;
    }
    const ok = await confirm({
      title: 'Unsaved changes',
      description: 'You have unsaved changes that will be lost. Are you sure you want to leave?',
      confirmLabel: 'Discard',
      variant: 'destructive',
    });
    if (ok) onLeave();
  }, [isDirty, onLeave, confirm]);

  return { guardedBack };
}
