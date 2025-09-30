import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'

import { Toaster } from './components/ui/sonner'
import { TooltipProvider } from './components/ui/tooltip'
import { createQueryClient } from './lib/query-client'
import { ThemeProvider } from './providers/theme-provider'
import { createAppRouter } from './router'

const queryClient = createQueryClient()
const router = createAppRouter(queryClient)

export const App = () => {
  return (
    <QueryClientProvider client={createQueryClient()}>
      <ThemeProvider defaultTheme="light" storageKey="wp-sms-theme">
        <TooltipProvider>
          <RouterProvider router={router} context={{ queryClient }} />
          <Toaster position="top-center" richColors toastOptions={{ className: '!p-4' }} />
        </TooltipProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}
