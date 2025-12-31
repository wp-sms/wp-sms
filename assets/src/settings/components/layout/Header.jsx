import React from 'react'
import { useSettings } from '@/context/SettingsContext'
import ThemeToggle from './ThemeToggle'
import { Menu } from 'lucide-react'

const pageTitles = {
  // Messaging
  'send-sms': 'Send SMS',
  outbox: 'Outbox',
  // Subscribers
  subscribers: 'Subscribers',
  groups: 'Groups',
  // Settings
  overview: 'Overview',
  gateway: 'SMS Gateway',
  phone: 'Phone Configuration',
  'message-button': 'Message Button',
  notifications: 'Notifications',
  newsletter: 'Newsletter',
  integrations: 'Integrations',
  advanced: 'Advanced Settings',
  // Privacy
  privacy: 'Privacy',
}

const pageDescriptions = {
  // Messaging
  'send-sms': 'Send SMS messages to subscribers, roles, or phone numbers',
  outbox: 'View and manage sent messages',
  // Subscribers
  subscribers: 'Manage your SMS subscribers',
  groups: 'Organize subscribers into groups',
  // Settings
  overview: 'Dashboard and quick stats',
  gateway: 'Configure your SMS gateway and credentials',
  phone: 'Admin mobile number settings',
  'message-button': 'Floating message button settings',
  notifications: 'WordPress event notifications',
  newsletter: 'SMS subscriber management',
  integrations: 'Third-party plugin integrations',
  advanced: 'Advanced configuration options',
  // Privacy
  privacy: 'GDPR data export and deletion',
}

export default function Header({ onMenuClick, showMenuButton }) {
  const { currentPage } = useSettings()
  const title = pageTitles[currentPage] || 'Settings'
  const description = pageDescriptions[currentPage] || ''

  return (
    <header className="wsms-flex wsms-items-center wsms-h-12 wsms-min-h-12 wsms-px-5 wsms-border-b wsms-border-border wsms-bg-card">
      {/* Mobile menu button */}
      {showMenuButton && (
        <button
          onClick={onMenuClick}
          className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-mr-3 wsms-rounded-md hover:wsms-bg-accent wsms-text-muted-foreground"
        >
          <Menu className="wsms-h-5 wsms-w-5" />
        </button>
      )}

      {/* Page title */}
      <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-min-w-0">
        <h1 className="wsms-text-[14px] wsms-font-semibold wsms-text-foreground wsms-truncate">
          {title}
        </h1>
        {description && (
          <span className="wsms-hidden sm:wsms-block wsms-text-[12px] wsms-text-muted-foreground wsms-truncate">
            {description}
          </span>
        )}
      </div>

      {/* Right side */}
      <div className="wsms-ml-auto wsms-flex wsms-items-center">
        <ThemeToggle />
      </div>
    </header>
  )
}
