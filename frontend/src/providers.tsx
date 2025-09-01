import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { PropsWithChildren } from 'react'

// Query client configuration
const createQueryClient = () => {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 5 * 60 * 1000, // 5 minutes
        refetchOnWindowFocus: false,
        retry: 1,
        experimental_prefetchInRender: true,
      },
      mutations: {
        retry: 1,
      },
    },
  })
}

// Global providers wrapper
const Providers = ({ children }: PropsWithChildren) => {
  const queryClient = createQueryClient()

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
}

export default Providers
