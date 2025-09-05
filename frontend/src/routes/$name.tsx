import { createFileRoute } from '@tanstack/react-router'

import SettingsDynamicPages from '@/pages/settings/dynamic-pages'

export const Route = createFileRoute('/$name')({
  component: SettingsDynamicPages,
})
