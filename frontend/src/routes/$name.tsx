import SettingsDynamicPages from '@/pages/settings/dynamic-pages'
import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/$name')({
  component: SettingsDynamicPages,
})
