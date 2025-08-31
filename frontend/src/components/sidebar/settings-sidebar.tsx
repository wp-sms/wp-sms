import clsx from 'clsx'
import { Settings } from 'lucide-react'

import { useGetSettingSchemaList } from '@/services/settings/use-get-setting-schema-list'
import { useSidebarStore } from '@/stores/use-sidebar-store'

import { SidebarGroup } from './sidebar-group'
import { SidebarItem } from './sidebar-item'
import { SidebarNestedItem } from './sidebar-nested-item'

export const SettingsSidebar: React.FC = () => {
  const { data: settingSchemaList } = useGetSettingSchemaList()
  const { isOpen } = useSidebarStore()

  return (
    <aside
      className={clsx(
        'bg-white p-4 border-r border-r-border overflow-hidsden !transition-all',
        isOpen ? 'w-72' : 'w-auto'
      )}
    >
      <div className="flex flex-col gap-y-10 sticky top-12 transition-all z-10">
        {isOpen && (
          <section className="flex items-center gap-x-2.5">
            <div className="size-10 rounded-lg bg-primary flex items-center justify-center">
              <Settings className="text-white" size={21} />
            </div>

            <div className="flex flex-col">
              <span className="text-gray-900 font-medium">WP SMS</span>
              <span className="text-gray-500">Settings</span>
            </div>
          </section>
        )}

        <section className="flex flex-col gap-y-5">
          {settingSchemaList?.data?.core && (
            <SidebarGroup title="Core Settings" showTitle={!!isOpen}>
              {Object.entries(settingSchemaList?.data?.core ?? {})?.map(([key, value]) => {
                return (
                  <SidebarItem
                    showTitle={!!isOpen}
                    key={`core-item-${value?.name}`}
                    href={value?.name}
                    icon={value?.icon}
                    title={value?.label}
                  />
                )
              })}
            </SidebarGroup>
          )}

          {settingSchemaList?.data?.addons && (
            <SidebarGroup title="Addons" showTitle={!!isOpen}>
              {Object.entries(settingSchemaList?.data?.addons ?? {})?.map(([key, value]) => {
                return (
                  <SidebarItem
                    showTitle={!!isOpen}
                    key={`addon-item-${value?.name}`}
                    href={value?.name}
                    icon={value?.icon}
                    title={value?.label}
                  />
                )
              })}
            </SidebarGroup>
          )}

          {settingSchemaList?.data?.integrations && (
            <SidebarGroup title="Integrations" showTitle={!!isOpen}>
              {Object.entries(settingSchemaList?.data?.integrations?.children ?? {})?.map(([key, value]) => {
                return (
                  <SidebarNestedItem
                    key={`integrations-item-${value?.label}-${key}`}
                    title={value?.label}
                    items={value?.children}
                    showTitle={!!isOpen}
                  />
                )
              })}
            </SidebarGroup>
          )}
        </section>
      </div>
    </aside>
  )
}
