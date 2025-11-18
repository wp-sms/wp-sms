export interface GlobalsData {
  nonce: string
  restUrl: string
  pluginVersion: string
  frontend_build_url: string
  jsonPath: string
  react_starting_point: string
}

export interface HeaderNavItem {
  title: string
  url: string
  icon: string
  description: string
  isExternal: boolean
}

export interface SidebarSchemaItem {
  name: string
  label: string
  icon: string
}

export interface SidebarIntegrationGroup {
  label: string
  children: Record<string, SidebarSchemaItem>
}

export interface SidebarSchema {
  core?: Record<string, SidebarSchemaItem>
  addons?: Record<string, SidebarSchemaItem>
  integrations?: {
    label: string
    children: Record<string, SidebarIntegrationGroup>
  }
}

export interface LayoutData {
  header: HeaderNavItem[]
  sidebar: SidebarSchema
}

declare global {
  interface Window {
    WP_SMS_DATA?: {
      globals: GlobalsData
      layout: LayoutData
    }

    wp: unknown
  }
}
