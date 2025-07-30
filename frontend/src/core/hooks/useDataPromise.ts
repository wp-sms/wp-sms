'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import type { Nullable } from '../types/helpers';

/**
 * A custom hook that converts data and error states into a promise-based structure.
 * It provides a `getPromise` function that resolves when data is available and rejects on an error.
 *
 * @param data - The data that, when available, resolves the promise.
 * @param isError - A boolean indicating if an error occurred.
 * @param errorMessage - Optional error message to be used in case of rejection.
 * @returns An object containing:
 * - `getPromise`: A function that returns a promise resolving to the data.
 * - `isLoading`: A boolean indicating if the data is still loading.
 * - `isDone`: A boolean indicating if the data retrieval is complete.
 */
export function useDataPromise<T>(data: T | undefined, isError: boolean, errorMessage?: string) {
    // Ref to store the resolver function for the promise
    const resolverRef = useRef<((value: T) => void) | null>(null);
    // Ref to store the rejecter function for the promise
    const rejecterRef = useRef<Nullable<VoidFunction>>(null);

    // Tracks whether the data is still loading
    const [isLoading, setIsLoading] = useState(false);
    // Tracks whether the promise resolution is complete
    const [isDone, setIsDone] = useState(false);

    // Cleanup function to reset state
    const cleanup = useCallback(() => {
        resolverRef.current = null;
        rejecterRef.current = null;
        setIsLoading(false);
        setIsDone(true);
    }, []);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (rejecterRef.current) {
                rejecterRef.current();
            }
            cleanup();
        };
    }, [cleanup]);

    useEffect(() => {
        if (isError) {
            if (rejecterRef.current) {
                rejecterRef.current();
                cleanup();
            }
        } else if (data !== undefined) {
            if (resolverRef.current) {
                resolverRef.current(data);
                cleanup();
            }
        }
    }, [data, isError, cleanup]);

    /**
     * Returns a promise that resolves when data is available or rejects on error.
     *
     * @returns A promise resolving to the data or rejecting on an error.
     */
    const getPromise = useCallback((): Promise<T> => {
        if (isError) {
            setIsDone(true);
            setIsLoading(false);
            return Promise.reject(new Error(errorMessage || 'Operation failed'));
        } else if (data !== undefined) {
            setIsDone(true);
            setIsLoading(false);
            return Promise.resolve(data);
        } else {
            // If data is not yet available, create a new promise and store its resolver and rejecter
            setIsLoading(true);
            setIsDone(false);
            return new Promise<T>((resolve, reject) => {
                resolverRef.current = resolve;
                rejecterRef.current = reject;
            });
        }
    }, [data, isError, errorMessage]);

    return { getPromise, isLoading, isDone };
}
