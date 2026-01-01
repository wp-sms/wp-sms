import React, { useState, useEffect } from 'react'
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
  ChevronRight,
  Cog,
  Mail,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useSettings } from '@/context/SettingsContext'
import { getWpSettings } from '@/lib/utils'

// Navigation structure - flat items + Settings submenu
const navigation = [
  // Direct items
  { type: 'item', id: 'send-sms', label: 'Send SMS', icon: Send },
  { type: 'item', id: 'outbox', label: 'Outbox', icon: Inbox },
  { type: 'item', id: 'subscribers', label: 'Subscribers', icon: Users },
  { type: 'item', id: 'groups', label: 'Groups', icon: FolderOpen },

  // Settings Section - collapsible with sub-items
  {
    type: 'group',
    id: 'settings',
    label: 'Settings',
    icon: Cog,
    defaultExpanded: false,
    items: [
      { id: 'overview', label: 'Overview', icon: LayoutDashboard },
      { id: 'gateway', label: 'Gateway', icon: Radio },
      { id: 'phone', label: 'Phone', icon: Phone },
      { id: 'message-button', label: 'Message Button', icon: MessageSquare },
      { id: 'notifications', label: 'Notifications', icon: Bell },
      { id: 'newsletter', label: 'Newsletter', icon: Mail },
      { id: 'integrations', label: 'Integrations', icon: Puzzle },
      { id: 'advanced', label: 'Advanced', icon: Settings },
    ],
  },

  // Privacy (conditional) - direct item
  { type: 'item', id: 'privacy', label: 'Privacy', icon: Shield, condition: 'gdprEnabled' },
]

const links = [
  { label: 'Documentation', href: 'https://wp-sms-pro.com/documentation/' },
  { label: 'Support', href: 'https://wordpress.org/support/plugin/wp-sms/' },
]

// Single nav item (leaf node)
function NavItem({ item, isActive, onClick, isNested = false }) {
  const Icon = item.icon
  return (
    <button
      onClick={onClick}
      className={cn(
        'wsms-flex wsms-w-full wsms-items-center wsms-gap-3 wsms-rounded-md wsms-text-[13px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-left',
        isNested ? 'wsms-py-2 wsms-px-3 wsms-pl-10' : 'wsms-px-3 wsms-py-2.5',
        isActive
          ? 'wsms-bg-primary wsms-text-primary-foreground wsms-shadow-sm'
          : 'wsms-text-foreground/70 hover:wsms-bg-accent hover:wsms-text-foreground'
      )}
    >
      <Icon
        className={cn('wsms-h-4 wsms-w-4 wsms-shrink-0', isNested && 'wsms-h-3.5 wsms-w-3.5')}
        strokeWidth={1.75}
      />
      <span className={cn(isNested && 'wsms-text-[12px]')}>{item.label}</span>
    </button>
  )
}

// Collapsible group component
function NavGroup({ group, currentPage, setCurrentPage, conditions }) {
  // Check if any item in this group is active
  const hasActiveChild = group.items.some((item) => item.id === currentPage)

  // Initialize expanded state - expanded if has active child or defaultExpanded
  const [isExpanded, setIsExpanded] = useState(hasActiveChild || group.defaultExpanded || false)

  // Auto-expand when a child becomes active
  useEffect(() => {
    if (hasActiveChild && !isExpanded) {
      setIsExpanded(true)
    }
  }, [hasActiveChild])

  // Filter items based on conditions
  const filteredItems = group.items.filter((item) => {
    if (!item.condition) return true
    return conditions[item.condition]
  })

  // Don't render empty groups
  if (filteredItems.length === 0) return null

  const Icon = group.icon

  return (
    <div className="wsms-space-y-0.5">
      {/* Group header - clickable to expand/collapse */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-px-3 wsms-py-2.5 wsms-text-[13px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-left',
          hasActiveChild
            ? 'wsms-text-primary wsms-bg-primary/5'
            : 'wsms-text-foreground/80 hover:wsms-bg-accent hover:wsms-text-foreground'
        )}
      >
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          <Icon className="wsms-h-[18px] wsms-w-[18px] wsms-shrink-0" strokeWidth={1.5} />
          <span>{group.label}</span>
        </div>
        <ChevronRight
          className={cn(
            'wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-transition-transform wsms-duration-200',
            isExpanded && 'wsms-rotate-90'
          )}
          strokeWidth={1.5}
        />
      </button>

      {/* Expandable content with smooth animation */}
      <div
        className={cn(
          'wsms-overflow-hidden wsms-transition-all wsms-duration-200 wsms-ease-out',
          isExpanded ? 'wsms-max-h-[500px] wsms-opacity-100' : 'wsms-max-h-0 wsms-opacity-0'
        )}
      >
        <div className="wsms-relative wsms-py-1">
          {/* Subtle connecting line */}
          <div className="wsms-absolute wsms-left-[22px] wsms-top-2 wsms-bottom-2 wsms-w-px wsms-bg-border/50" />

          {filteredItems.map((item) => (
            <NavItem
              key={item.id}
              item={item}
              isActive={currentPage === item.id}
              onClick={() => setCurrentPage(item.id)}
              isNested={true}
            />
          ))}
        </div>
      </div>
    </div>
  )
}

export default function Sidebar({ onClose, showClose }) {
  const { currentPage, setCurrentPage } = useSettings()
  const version = window.wpSmsSettings?.version || '7.0'
  const { gdprEnabled } = getWpSettings()

  // Conditions object for filtering
  const conditions = {
    gdprEnabled,
  }

  // Filter navigation items based on conditions
  const filteredNavigation = navigation.filter((item) => {
    if (!item.condition) return true
    return conditions[item.condition]
  })

  return (
    <div className="wsms-flex wsms-flex-col wsms-h-full wsms-min-h-0 wsms-bg-card">
      {/* Mobile close button */}
      {showClose && (
        <div className="wsms-flex wsms-justify-end wsms-p-3 wsms-border-b wsms-border-border">
          <button
            onClick={onClose}
            aria-label="Close navigation menu"
            className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-md hover:wsms-bg-accent wsms-text-muted-foreground wsms-transition-colors"
          >
            <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
          </button>
        </div>
      )}

      {/* Navigation */}
      <nav className="wsms-flex-1 wsms-min-h-0 wsms-overflow-y-auto wsms-px-3 wsms-py-4 wsms-scrollbar-thin">
        <div className="wsms-space-y-1">
          {filteredNavigation.map((item) =>
            item.type === 'group' ? (
              <NavGroup
                key={item.id}
                group={item}
                currentPage={currentPage}
                setCurrentPage={setCurrentPage}
                conditions={conditions}
              />
            ) : (
              <NavItem
                key={item.id}
                item={item}
                isActive={currentPage === item.id}
                onClick={() => setCurrentPage(item.id)}
              />
            )
          )}
        </div>
      </nav>

      {/* Footer */}
      <div className="wsms-border-t wsms-border-border wsms-px-3 wsms-py-4 wsms-mt-auto wsms-bg-muted/30">
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
