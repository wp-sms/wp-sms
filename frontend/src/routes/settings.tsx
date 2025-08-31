import { lazy } from 'react'
import { Navigate,Route, Routes } from 'react-router-dom'

const SettingsDynamicPages = lazy(() => import('@/pages/settings/dynamic-pages'))

const SettingsRoutes = () => {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="general" replace />} />
      <Route path=":name" element={<SettingsDynamicPages />} />
    </Routes>
  )
}

export default SettingsRoutes
