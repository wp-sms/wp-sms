import { type DependencyList, useCallback, useEffect, useRef } from "react";
/**
 * An stable callback for use in useEffect that prevents infinite loops and ensures up-to-date execution..
 *
 * @param callback - The function you want to keep stable across re-renders.
 * @param dependencies - Dependencies for the useCallback.
 * @returns A stable version of the callback that can be used without dependency issues in useEffect without passing it as dependency.
 */
export function useStableCallback<T extends (...args: any[]) => any>(
  callback: T,
  dependencies: DependencyList
): T {
  const memoizedCallback = useCallback(callback, dependencies);

  // Use useRef to store the latest version of the callback
  const stableCallbackRef = useRef<T>(memoizedCallback);

  // Update the ref whenever the callback changes
  useEffect(() => {
    stableCallbackRef.current = memoizedCallback;
  }, [memoizedCallback]);

  return useCallback((...args: Parameters<T>): ReturnType<T> => {
    return stableCallbackRef.current(...args);
  }, []) as T;
}
