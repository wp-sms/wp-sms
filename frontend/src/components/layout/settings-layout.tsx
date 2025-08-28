import { SettingsSidebar } from '../sidebar/settings-sidebar'

import { SettingsHeader } from './settings-header'
import type { PropsWithChildren } from 'react'

type SettingsLayoutProps = PropsWithChildren

export const SettingsLayout = ({ children }: SettingsLayoutProps) => {
  return (
    <div className="wrap flex w-full min-h-screen">
      <SettingsSidebar />

      <div className="flex-1 bg-white">
        <SettingsHeader />

        <main>{children}</main>
      </div>
    </div>
  )
}
