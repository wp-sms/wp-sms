import React from 'react'
import {
  LayoutDashboard,
  Radio,
  Phone,
  MessageSquare,
  Bell,
  Users,
  Puzzle,
  Settings,
  ExternalLink,
  X,
  Send,
  Inbox,
  FolderOpen,
  Shield,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useSettings } from '@/context/SettingsContext'
import { getWpSettings } from '@/lib/utils'

const navigation = [
  // Messaging Section
  { type: 'label', label: 'Messaging' },
  { id: 'send-sms', label: 'Send SMS', icon: Send },
  { id: 'outbox', label: 'Outbox', icon: Inbox },
  // Subscribers Section
  { type: 'label', label: 'Subscribers' },
  { id: 'subscribers', label: 'Subscribers', icon: Users },
  { id: 'groups', label: 'Groups', icon: FolderOpen },
  // Settings Section
  { type: 'label', label: 'Settings' },
  { id: 'overview', label: 'Overview', icon: LayoutDashboard },
  { id: 'gateway', label: 'Gateway', icon: Radio },
  { id: 'phone', label: 'Phone', icon: Phone },
  { id: 'message-button', label: 'Message Button', icon: MessageSquare },
  { id: 'notifications', label: 'Notifications', icon: Bell },
  { id: 'newsletter', label: 'Newsletter', icon: Users },
  { id: 'integrations', label: 'Integrations', icon: Puzzle },
  { id: 'advanced', label: 'Advanced', icon: Settings },
  // Privacy Section (conditional)
  { type: 'label', label: 'Privacy', condition: 'gdprEnabled' },
  { id: 'privacy', label: 'Privacy', icon: Shield, condition: 'gdprEnabled' },
]

const links = [
  { label: 'Documentation', href: 'https://wp-sms-pro.com/documentation/' },
  { label: 'Support', href: 'https://wordpress.org/support/plugin/wp-sms/' },
]

function NavItem({ item, isActive, onClick }) {
  const Icon = item.icon
  return (
    <button
      onClick={onClick}
      className={cn(
        'wsms-flex wsms-w-full wsms-items-center wsms-gap-3 wsms-rounded-md wsms-px-3 wsms-py-2.5 wsms-text-[13px] wsms-font-medium wsms-transition-colors wsms-text-left',
        isActive
          ? 'wsms-bg-primary wsms-text-primary-foreground'
          : 'wsms-text-foreground/80 hover:wsms-bg-accent hover:wsms-text-foreground'
      )}
    >
      <Icon className="wsms-h-[18px] wsms-w-[18px] wsms-shrink-0" strokeWidth={1.5} />
      <span>{item.label}</span>
    </button>
  )
}

// Section label component
function SectionLabel({ label }) {
  return (
    <div className="wsms-px-3 wsms-pt-4 wsms-pb-1">
      <span className="wsms-text-[10px] wsms-font-semibold wsms-uppercase wsms-tracking-wider wsms-text-muted-foreground">
        {label}
      </span>
    </div>
  )
}

export default function Sidebar({ onClose, showClose }) {
  const { currentPage, setCurrentPage } = useSettings()
  const version = window.wpSmsSettings?.version || '7.0'
  const isProActive = window.wpSmsSettings?.addons?.pro
  const { gdprEnabled } = getWpSettings()

  // Filter navigation items based on conditions
  const filteredNavigation = navigation.filter((item) => {
    if (!item.condition) return true
    if (item.condition === 'gdprEnabled') return gdprEnabled
    return true
  })

  return (
    <div className="wsms-flex wsms-flex-col wsms-h-full wsms-min-h-0">
      {/* Header */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-h-12 wsms-min-h-12 wsms-px-5 wsms-border-b wsms-border-border">
        <div className="wsms-flex wsms-items-center wsms-gap-2.5">
          <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md wsms-bg-primary wsms-text-primary-foreground">
            <Radio className="wsms-h-[18px] wsms-w-[18px]" strokeWidth={1.5} />
          </div>
          <div className="wsms-flex wsms-items-center wsms-gap-2">
            <span className="wsms-text-[14px] wsms-font-semibold wsms-text-foreground">WP SMS</span>
            {isProActive && (
              <span className="wsms-text-[10px] wsms-font-medium wsms-uppercase wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-bg-primary/10 wsms-text-primary">
                Pro
              </span>
            )}
          </div>
        </div>
        {showClose && (
          <button
            onClick={onClose}
            className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-md hover:wsms-bg-accent wsms-text-muted-foreground"
          >
            <X className="wsms-h-4 wsms-w-4" />
          </button>
        )}
      </div>

      {/* Navigation */}
      <nav className="wsms-flex-1 wsms-min-h-0 wsms-overflow-y-auto wsms-px-3 wsms-py-1 wsms-scrollbar-thin">
        <div className="wsms-space-y-0.5">
          {filteredNavigation.map((item, index) => {
            if (item.type === 'label') {
              return <SectionLabel key={`label-${item.label}`} label={item.label} />
            }
            return (
              <NavItem
                key={item.id}
                item={item}
                isActive={currentPage === item.id}
                onClick={() => setCurrentPage(item.id)}
              />
            )
          })}
        </div>
      </nav>

      {/* Footer */}
      <div className="wsms-border-t wsms-border-border wsms-px-3 wsms-py-4 wsms-mt-auto">
        <div className="wsms-space-y-1 wsms-mb-3">
          {links.map((link) => (
            <a
              key={link.label}
              href={link.href}
              target="_blank"
              rel="noopener noreferrer"
              className="wsms-flex wsms-items-center wsms-justify-between wsms-px-3 wsms-py-2 wsms-text-[12px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors wsms-rounded-md hover:wsms-bg-accent"
            >
              <span>{link.label}</span>
              <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
            </a>
          ))}
        </div>
        <div className="wsms-px-3 wsms-text-[11px] wsms-text-muted-foreground">
          Version {version}
        </div>
      </div>
    </div>
  )
}
