import { createFileRoute, Outlet } from '@tanstack/react-router'

import { SettingsLayout } from '@/components/layout/settings-layout'

export const Route = createFileRoute('/settings/_layout')({
  component: RouteComponent,
})

function RouteComponent() {
  return (
    <>
      <SettingsLayout>
        <Outlet />
      </SettingsLayout>
    </>
  )
}
