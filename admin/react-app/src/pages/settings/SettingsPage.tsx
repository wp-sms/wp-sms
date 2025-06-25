import { useState } from "react"
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider
} from "../../components/ui/sidebar"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../components/ui/card"
import { GeneralSettings } from "./general"
import { GatewaySettings } from "./gateway"
import { AdvancedSettings } from "./advanced"
import { Settings, MessageSquare, Zap } from "lucide-react"

type SettingsTab = "general" | "gateway" | "advanced"

export function SettingsPage() {
  const [activeTab, setActiveTab] = useState<SettingsTab>("general")

  const renderContent = () => {
    switch (activeTab) {
      case "general":
        return (
          <Card>
            <CardHeader>
              <CardTitle>General Settings</CardTitle>
              <CardDescription>
                Configure basic SMS plugin settings and preferences.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <GeneralSettings />
            </CardContent>
          </Card>
        )
      case "gateway":
        return (
          <Card>
            <CardHeader>
              <CardTitle>Gateway Settings</CardTitle>
              <CardDescription>
                Configure your SMS gateway provider and connection settings.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <GatewaySettings />
            </CardContent>
          </Card>
        )
      case "advanced":
        return (
          <Card>
            <CardHeader>
              <CardTitle>Advanced Settings</CardTitle>
              <CardDescription>
                Advanced configuration options for power users and developers.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <AdvancedSettings />
            </CardContent>
          </Card>
        )
      default:
        return null
    }
  }

  return (
    <SidebarProvider>
      <div className="flex w-full min-h-[600px]">
        <Sidebar style={{ width: 256, minWidth: 256, background: "#f8f9fa", borderRight: "1px solid #e5e7eb" }}>
          <SidebarHeader>
            <div className="flex items-center gap-2 px-2">
              <Settings className="h-4 w-4" />
              <span className="font-semibold">Settings</span>
            </div>
          </SidebarHeader>
          <SidebarContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton
                  isActive={activeTab === "general"}
                  onClick={() => setActiveTab("general")}
                  tooltip="General Settings"
                >
                  <Settings className="h-4 w-4" />
                  <span>General</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
              <SidebarMenuItem>
                <SidebarMenuButton
                  isActive={activeTab === "gateway"}
                  onClick={() => setActiveTab("gateway")}
                  tooltip="Gateway Settings"
                >
                  <MessageSquare className="h-4 w-4" />
                  <span>Gateway</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
              <SidebarMenuItem>
                <SidebarMenuButton
                  isActive={activeTab === "advanced"}
                  onClick={() => setActiveTab("advanced")}
                  tooltip="Advanced Settings"
                >
                  <Zap className="h-4 w-4" />
                  <span>Advanced</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarContent>
        </Sidebar>
        <div className="flex-1 p-6">
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">Settings</h1>
              <p className="text-muted-foreground">
                Manage your SMS plugin configuration and preferences.
              </p>
            </div>
            {renderContent()}
          </div>
        </div>
      </div>
    </SidebarProvider>
  )
}