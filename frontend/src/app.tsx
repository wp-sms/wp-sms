import { Toaster } from './components/ui/sonner'
import { QueryClientProvider } from '@tanstack/react-query'
import { createQueryClient } from './lib/query-client'
import { createAppRouter } from './router'
import { RouterProvider } from '@tanstack/react-router'

const queryClient = createQueryClient()
const router = createAppRouter(queryClient)

export const App = () => {
  return (
    <QueryClientProvider client={createQueryClient()}>
      <RouterProvider router={router} context={{ queryClient }} />
      <Toaster />
    </QueryClientProvider>
  )
}
