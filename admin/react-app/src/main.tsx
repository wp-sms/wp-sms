import { createRoot } from 'react-dom/client'
import './index.css'
import { SettingsPage } from './pages/settings/SettingsPage'

const root = createRoot(document.getElementById('wp-sms-settings-root')!)
root.render(<SettingsPage />)