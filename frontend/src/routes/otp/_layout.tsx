import { createFileRoute, Outlet } from '@tanstack/react-router'

import { OTPSidebar } from '@/components/sidebar/otp-sidebar'
import { SidebarInset } from '@/components/ui/sidebar'
import { SidebarProvider } from '@/providers/sidebar-provider'

export const Route = createFileRoute('/otp/_layout')({
  component: RouteComponent,
})

function RouteComponent() {
  return (
    <div className="wrap flex w-full min-h-screen relative">
      <SidebarProvider>
        <OTPSidebar />
        <SidebarInset>
          <main className="p-6 flex-1">
            <Outlet />
          </main>
        </SidebarInset>
      </SidebarProvider>
    </div>
  )
}
