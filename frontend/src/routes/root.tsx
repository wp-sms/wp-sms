import { Route, Routes } from 'react-router-dom'

import SettingsRoutes from './settings'

const AppRoutes = () => {
  return (
    <Routes>
      {/* Settings routes */}
      <Route path="/*" element={<SettingsRoutes />} />

      {/* Add more route groups here as needed */}
      {/* Example: <Route path="/analytics/*" element={<AnalyticsRoutes />} /> */}
    </Routes>
  )
}

export default AppRoutes
