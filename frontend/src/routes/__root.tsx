import { createRootRouteWithContext, Outlet } from '@tanstack/react-router'
import { TanStackRouterDevtools } from '@tanstack/react-router-devtools'

import { SettingsLayout } from '@/components/layout/settings-layout'
import type { RouterContext } from '@/types/global'

const RootLayout = () => (
  <>
    <SettingsLayout>
      <Outlet />
    </SettingsLayout>
    <TanStackRouterDevtools position="top-right" />
  </>
)

export const Route = createRootRouteWithContext<RouterContext>()({ component: RootLayout })
