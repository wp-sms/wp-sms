import { createFileRoute, Outlet, useLocation } from '@tanstack/react-router'

import { Header } from '@/components/layout/header'
import { OTPSidebar } from '@/components/sidebar/otp-sidebar'

export const Route = createFileRoute('/otp/_layout')({
  component: RouteComponent,
})

function RouteComponent() {
  const location = useLocation()

  return (
    <div className="wrap flex w-full min-h-screen">
      <OTPSidebar />

      <div className="flex-1 bg-white">
        <Header currentPage={location.pathname.split('/').pop() || 'Activity'} />

        <main className="p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
