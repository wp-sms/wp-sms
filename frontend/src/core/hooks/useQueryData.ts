import {
  isServer,
  useIsFetching,
  useQueryClient,
  type QueryKey,
} from "@tanstack/react-query";
import { useEffect, useState } from "react";

/**
 * A custom hook to retrieve and manage query data from React Query.
 *
 * @template T - The expected data type of the query.
 * @param queryKey - The query key to retrieve data for.
 * @returns An object containing:
 * - `data`: The fetched query data.
 * - `isFetching`: A boolean indicating if the query is still fetching.
 */
export function useQueryData<T>(queryKey: QueryKey) {
  // Access the React Query client
  const queryClient = useQueryClient();

  // Get query data from the cache
  const data = queryClient.getQueryData<T>(queryKey);

  // Check if the query is currently fetching
  const isFetching = useIsFetching({ queryKey });

  // State to track loading status
  const [isLoading, setIsLoading] = useState<boolean>(!data);

  useEffect(() => {
    if (isServer || data) {
      setIsLoading(false);
    } else {
      setIsLoading(isFetching > 0);
    }
  }, [isFetching, data]);

  return {
    data, // The fetched query data
    isFetching: isLoading, // Whether the query is still fetching
  };
}
