import { Routes, Route, useLocation } from "react-router-dom"
import { AppSidebar } from "./components/layout/sidebar"
import { SidebarInset, SidebarProvider, SidebarTrigger } from "./components/ui/sidebar"
import { Separator } from "./components/ui/separator"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "./components/ui/breadcrumb"
import { SettingsPage } from "./pages/settings"

function App() {
  const location = useLocation()

  const getPageTitle = () => {
    const path = location.pathname
    if (path === "/" || path === "/settings") return "Settings"
    if (path === "/dashboard") return "Dashboard"
    if (path === "/messages") return "Messages"
    if (path === "/subscribers") return "Subscribers"
    if (path === "/reports") return "Reports"
    return "WP SMS"
  }

  const getBreadcrumbSection = () => {
    const path = location.pathname
    if (path.startsWith("/settings")) return "Settings"
    if (path === "/dashboard") return "Dashboard"
    if (path === "/messages") return "Messages"
    if (path === "/subscribers") return "Subscribers"
    if (path === "/reports") return "Reports"
    return "Admin"
  }

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator orientation="vertical" className="mr-2 h-4" />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href="/wp-admin/admin.php?page=wp-sms">WP SMS</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem className="hidden md:block">
                <BreadcrumbLink href="/">
                  {getBreadcrumbSection()}
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator className="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>{getPageTitle()}</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4">
          <Routes>
            <Route path="/" element={<SettingsPage />} />
            <Route path="/settings" element={<SettingsPage />} />
            <Route path="/dashboard" element={<div className="text-center p-8"><h1 className="text-2xl font-bold">Dashboard</h1><p className="text-muted-foreground">Coming soon...</p></div>} />
            <Route path="/messages" element={<div className="text-center p-8"><h1 className="text-2xl font-bold">Messages</h1><p className="text-muted-foreground">Coming soon...</p></div>} />
            <Route path="/subscribers" element={<div className="text-center p-8"><h1 className="text-2xl font-bold">Subscribers</h1><p className="text-muted-foreground">Coming soon...</p></div>} />
            <Route path="/reports" element={<div className="text-center p-8"><h1 className="text-2xl font-bold">Reports</h1><p className="text-muted-foreground">Coming soon...</p></div>} />
          </Routes>
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}

export default App