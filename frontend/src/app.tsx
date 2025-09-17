import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'

import { Toaster } from './components/ui/sonner'
import { createQueryClient } from './lib/query-client'
import { createAppRouter } from './router'

const queryClient = createQueryClient()
const router = createAppRouter(queryClient)

export const App = () => {
  return (
    <QueryClientProvider client={createQueryClient()}>
      <RouterProvider router={router} context={{ queryClient }} />
      <Toaster position="top-center" richColors toastOptions={{ className: '!p-4' }} />
    </QueryClientProvider>
  )
}
