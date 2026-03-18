import { useState, useRef, useCallback, useEffect } from 'react';

export function useDeleteConfirm(deleteFn: (id: string) => Promise<void>) {
  const [confirmingId, setConfirmingId] = useState<string | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout>>();

  const handleDelete = useCallback(async (id: string) => {
    if (confirmingId === id) {
      clearTimeout(timeoutRef.current);
      setConfirmingId(null);
      await deleteFn(id);
    } else {
      clearTimeout(timeoutRef.current);
      setConfirmingId(id);
      timeoutRef.current = setTimeout(() => setConfirmingId(null), 3000);
    }
  }, [confirmingId, deleteFn]);

  const isConfirming = useCallback((id: string) => confirmingId === id, [confirmingId]);

  useEffect(() => {
    return () => clearTimeout(timeoutRef.current);
  }, []);

  return { handleDelete, isConfirming };
}
