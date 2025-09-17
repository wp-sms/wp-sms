import { createFileRoute, Outlet, useParams } from '@tanstack/react-router'

import { Header } from '@/components/layout/header'
import { SettingsSidebar } from '@/components/sidebar/settings-sidebar'

export const Route = createFileRoute('/settings/_layout')({
  component: RouteComponent,
})

function RouteComponent() {
  const { name } = useParams({ from: '/settings/_layout/$name' })

  return (
    <div className="wrap flex w-full min-h-screen">
      <SettingsSidebar />

      <div className="flex-1 bg-white">
        <Header currentPage={name || 'General'} />

        <main className="p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
