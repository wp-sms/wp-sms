import { useQueryClient, type QueryKey } from "@tanstack/react-query";
import { useCallback, useState } from "react";

/**
 * A custom hook to invalidate a query in React Query, triggering a refetch.
 *
 * @param queryKey - The query key to be invalidated.
 * @returns An object containing:
 * - `invalidateQuery`: A function to invalidate the query.
 * - `isInvalidating`: A boolean indicating if the invalidation process is in progress.
 */
export function useInvalidateQuery(queryKey: QueryKey) {
  // State to track whether the query is currently being invalidated
  const [isInvalidating, setIsInvalidating] = useState(false);

  // Access the React Query client
  const queryClient = useQueryClient();

  /**
   * Invalidates the given query key, causing it to refetch.
   */
  const invalidateQuery = useCallback(async () => {
    setIsInvalidating(true);

    try {
      await queryClient.invalidateQueries({
        queryKey: queryKey,
        type: "all",
      });
    } catch (error) {
    } finally {
      setIsInvalidating(false);
    }
  }, [queryKey, queryClient]);

  return {
    invalidateQuery, // Function to trigger query invalidation
    isInvalidating, // Boolean indicating if invalidation is in progress
  };
}
