import { QueryClient } from '@tanstack/react-query';

const isServer = typeof window === 'undefined';

/**
 * Stores a singleton **QueryClient** instance for the browser.
 */
let browserQueryClient: QueryClient | undefined = undefined;

/**
 * Creates a new **QueryClient** instance with predefined default options.
 *
 * @returns {QueryClient} A new instance of QueryClient.
 */
export function makeQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60 * 1000 * 5, // Data is considered fresh for 5 minutes (reduces unnecessary refetching)
                refetchOnWindowFocus: false, // Prevents automatic refetching when the window regains focus
                retry: 1, // Retries failed queries only once
                experimental_prefetchInRender: true, // Enables experimental prefetching during render
            },
        },
    });
}

/**
 * Retrieves or creates a **QueryClient** instance.
 *
 * - If **running on the server**, returns a new `QueryClient` instance (stateless).
 * - If **running in the browser**, returns a singleton instance to persist cache across renders.
 *
 * @returns {QueryClient} A QueryClient instance.
 */
export function getQueryClient(): QueryClient {
    if (isServer) {
        return makeQueryClient(); // Always return a new instance on the server
    } else {
        if (!browserQueryClient) {
            browserQueryClient = makeQueryClient(); // Create a singleton instance in the browser
        }
        return browserQueryClient;
    }
}
