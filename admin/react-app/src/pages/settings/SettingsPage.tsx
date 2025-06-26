import { useState, useEffect } from "react"
import { SidebarProvider } from "@/components/ui/sidebar"
import { DynamicSidebar } from "@/components/layout/dynamic-sidebar"
import { DynamicForm } from "@/components/forms/dynamic-form"
import { useGroupSchema } from "@/hooks/use-group-schema"
import { useGroupValues } from "@/hooks/use-group-values"

export function SettingsPage() {
  // Get initial group from URL or default to 'general'
  const getInitialGroup = (): string => {
    const urlParams = new URLSearchParams(window.location.search)
    return urlParams.get('group') || 'general'
  }

  const [selectedGroup, setSelectedGroup] = useState<string>(getInitialGroup)
  const { data: groupSchema, loading: schemaLoading, error: schemaError } = useGroupSchema(selectedGroup)
  const { data: savedValues, loading: valuesLoading, error: valuesError } = useGroupValues(selectedGroup)

  // Update URL when group changes
  const handleGroupSelect = (group: string) => {
    setSelectedGroup(group)
    
    // Update URL without page reload
    const url = new URL(window.location.href)
    url.searchParams.set('group', group)
    window.history.pushState({}, '', url.toString())
  }

  // Handle browser back/forward buttons
  useEffect(() => {
    const handlePopState = () => {
      const newGroup = getInitialGroup()
      setSelectedGroup(newGroup)
    }

    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [])

  // Combine loading states
  const loading = schemaLoading || valuesLoading
  const error = schemaError || valuesError

  return (
    <SidebarProvider>
      <div className="flex w-full min-h-[600px]">
        <DynamicSidebar onGroupSelect={handleGroupSelect} selectedGroup={selectedGroup} />
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
              savedValues={savedValues}
              loading={loading} 
              error={error} 
            />
          </div>
        </div>
      </div>
    </SidebarProvider>
  )
} 