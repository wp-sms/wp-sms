import { createRootRouteWithContext, Outlet } from '@tanstack/react-router'
import { TanStackRouterDevtools } from '@tanstack/react-router-devtools'

import type { RouterContext } from '@/types/global'

const RootLayout = () => (
  <>
    <Outlet />
    <TanStackRouterDevtools position="top-right" />
  </>
)

export const Route = createRootRouteWithContext<RouterContext>()({ component: RootLayout })
