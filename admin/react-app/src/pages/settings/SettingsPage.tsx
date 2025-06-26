import { useState } from "react"
import { SidebarProvider } from "@/components/ui/sidebar"
import { DynamicSidebar } from "@/components/layout/dynamic-sidebar"
import { DynamicForm } from "@/components/forms/dynamic-form"
import { useGroupSchema } from "@/hooks/use-group-schema"

export function SettingsPage() {
  const [selectedGroup, setSelectedGroup] = useState<string | null>(null)
  const { data: groupSchema, loading, error } = useGroupSchema(selectedGroup)

  return (
    <SidebarProvider>
      <div className="flex w-full min-h-[600px]">
        <DynamicSidebar onGroupSelect={setSelectedGroup} />
        <div className="flex-1 p-6">
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">Settings</h1>
              <p className="text-muted-foreground">
                Manage your SMS plugin configuration and preferences.
              </p>
            </div>
            <DynamicForm 
              schema={groupSchema} 
              loading={loading} 
              error={error} 
            />
          </div>
        </div>
      </div>
    </SidebarProvider>
  )
}