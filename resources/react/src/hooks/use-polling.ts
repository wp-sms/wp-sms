import { useEffect, useRef } from 'react';

export function usePolling(callback: () => void, intervalMs: number, enabled = true): void {
  const savedCallback = useRef(callback);

  useEffect(() => {
    savedCallback.current = callback;
  }, [callback]);

  useEffect(() => {
    if (!enabled) return;

    let id: ReturnType<typeof setInterval> | undefined;

    function start() {
      id = setInterval(() => savedCallback.current(), intervalMs);
    }

    function handleVisibility() {
      if (document.hidden) {
        clearInterval(id);
        id = undefined;
      } else {
        savedCallback.current();
        start();
      }
    }

    start();
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
      clearInterval(id);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [intervalMs, enabled]);
}
