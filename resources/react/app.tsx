import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'

import { Toaster } from './components/ui/sonner'
import { CountriesProvider } from './context/countries-context'
import { createQueryClient } from './lib/query-client'
import { ThemeProvider } from './providers/theme-provider'
import { createAppRouter } from './router'

const queryClient = createQueryClient()
const router = createAppRouter(queryClient)

export const App = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider defaultTheme="light" storageKey="wp-sms-theme">
        <CountriesProvider>
          <RouterProvider router={router} context={{ queryClient }} />
          <Toaster position="top-center" richColors toastOptions={{ className: '!p-4' }} />
        </CountriesProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}
