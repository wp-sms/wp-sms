/**
 * Page Registry - Single source of truth for all page definitions
 *
 * This file centralizes page configuration that was previously spread across:
 * - SettingsContext.jsx (VALID_PAGES)
 * - AppShell.jsx (pages object + lazy imports)
 * - Sidebar.jsx (getNavigation)
 *
 * Benefits:
 * - Add/modify pages in one place
 * - Impossible to forget updating a file
 * - Clear overview of all pages and their properties
 * - Conditions and icons defined once
 */

import { lazy } from 'react'
import {
  LayoutDashboard,
  Radio,
  Phone,
  MessageSquare,
  Bell,
  Users,
  Puzzle,
  Settings,
  Send,
  Inbox,
  FolderOpen,
  Shield,
  Cog,
  Mail,
  ShoppingCart,
  Megaphone,
  RotateCcw,
  ArrowLeftRight,
  Terminal,
  CalendarClock,
} from 'lucide-react'
import { __ } from '@/lib/utils'

// Eager-loaded pages (needed immediately, no lazy loading)
import SendSms from '@/pages/SendSms'
import Overview from '@/pages/Overview'

// =============================================================================
// PAGE DEFINITIONS
// =============================================================================

/**
 * All pages in the application.
 *
 * Each page has:
 * - id: URL parameter value (e.g., ?tab=subscribers)
 * - label: Display name (use __() for i18n)
 * - icon: Lucide icon component
 * - component: React component (eager or lazy)
 * - nav: Navigation configuration
 *   - type: 'item' (top-level) or 'group-item' (nested under a group)
 *   - group: Parent group ID (if type is 'group-item')
 *   - order: Sort order within its level/group
 * - condition: Optional visibility condition (checked by Sidebar)
 */
export const pageDefinitions = {
  // ===== MESSAGING =====
  'send-sms': {
    label: () => __('Send SMS'),
    icon: Send,
    component: SendSms, // Eager loaded - first page users see
    nav: { type: 'item', order: 1 },
  },
  'outbox': {
    label: () => __('Outbox'),
    icon: Inbox,
    component: lazy(() => import('@/pages/Outbox')),
    nav: { type: 'item', order: 2 },
  },
  'scheduled': {
    label: () => __('Scheduled'),
    icon: CalendarClock,
    component: lazy(() => import('@/pages/Scheduled')),
    nav: { type: 'item', order: 2.5 },
    condition: 'hasProAddon',
  },

  // ===== SUBSCRIBERS =====
  'subscribers': {
    label: () => __('Subscribers'),
    icon: Users,
    component: lazy(() => import('@/pages/Subscribers')),
    nav: { type: 'item', order: 3 },
  },
  'groups': {
    label: () => __('Groups'),
    icon: FolderOpen,
    component: lazy(() => import('@/pages/Groups')),
    nav: { type: 'item', order: 4 },
  },

  // ===== PRIVACY =====
  'privacy': {
    label: () => __('Privacy'),
    icon: Shield,
    component: lazy(() => import('@/pages/Privacy')),
    nav: { type: 'item', order: 5 },
    condition: 'gdprEnabled',
  },

  // ===== SETTINGS (grouped) =====
  'overview': {
    label: () => __('Overview'),
    icon: LayoutDashboard,
    component: Overview, // Eager loaded
    nav: { type: 'group-item', group: 'settings', order: 1 },
  },
  'gateway': {
    label: () => __('Gateway'),
    icon: Radio,
    component: lazy(() => import('@/pages/Gateway')),
    nav: { type: 'group-item', group: 'settings', order: 2 },
  },
  'phone': {
    label: () => __('Phone'),
    icon: Phone,
    component: lazy(() => import('@/pages/PhoneConfig')),
    nav: { type: 'group-item', group: 'settings', order: 3 },
  },
  'message-button': {
    label: () => __('Message Button'),
    icon: MessageSquare,
    component: lazy(() => import('@/pages/MessageButton')),
    nav: { type: 'group-item', group: 'settings', order: 4 },
  },
  'notifications': {
    label: () => __('Notifications'),
    icon: Bell,
    component: lazy(() => import('@/pages/Notifications')),
    nav: { type: 'group-item', group: 'settings', order: 5 },
  },
  'newsletter': {
    label: () => __('Newsletter'),
    icon: Mail,
    component: lazy(() => import('@/pages/Newsletter')),
    nav: { type: 'group-item', group: 'settings', order: 6 },
  },
  'integrations': {
    label: () => __('Integrations'),
    icon: Puzzle,
    component: lazy(() => import('@/pages/Integrations')),
    nav: { type: 'group-item', group: 'settings', order: 7 },
  },
  'advanced': {
    label: () => __('Advanced'),
    icon: Settings,
    component: lazy(() => import('@/pages/Advanced')),
    nav: { type: 'group-item', group: 'settings', order: 8 },
  },

  // ===== WOOCOMMERCE PRO (add-on) =====
  'sms-campaigns': {
    label: () => __('SMS Campaigns'),
    icon: Megaphone,
    component: lazy(() => import('@/pages/SmsCampaigns')),
    nav: { type: 'group-item', group: 'woocommerce-pro', order: 1 },
    condition: 'hasWooCommercePro',
  },
  'cart-abandonment': {
    label: () => __('Cart Abandonment'),
    icon: RotateCcw,
    component: lazy(() => import('@/pages/CartAbandonment')),
    nav: { type: 'group-item', group: 'woocommerce-pro', order: 2 },
    condition: 'hasWooCommercePro',
  },
  'woocommerce-pro': {
    label: () => __('Settings'),
    icon: Settings,
    component: lazy(() => import('@/pages/WooCommercePro')),
    nav: { type: 'group-item', group: 'woocommerce-pro', order: 3 },
    condition: 'hasWooCommercePro',
  },

  // ===== TWO-WAY SMS (add-on) =====
  'two-way-inbox': {
    label: () => __('Inbox'),
    icon: Inbox,
    component: lazy(() => import('@/pages/TwoWayInbox')),
    nav: { type: 'group-item', group: 'two-way', order: 1 },
    condition: 'hasTwoWay',
  },
  'two-way-commands': {
    label: () => __('Commands'),
    icon: Terminal,
    component: lazy(() => import('@/pages/TwoWayCommands')),
    nav: { type: 'group-item', group: 'two-way', order: 2 },
    condition: 'hasTwoWay',
  },
  'two-way-settings': {
    label: () => __('Settings'),
    icon: Settings,
    component: lazy(() => import('@/pages/TwoWaySettings')),
    nav: { type: 'group-item', group: 'two-way', order: 3 },
    condition: 'hasTwoWay',
  },
}

// =============================================================================
// NAVIGATION GROUPS
// =============================================================================

/**
 * Groups for the sidebar navigation.
 * These are collapsible sections that contain multiple pages.
 */
export const navGroups = {
  'settings': {
    label: () => __('Settings'),
    icon: Cog,
    order: 10,
    defaultExpanded: false,
  },
  'woocommerce-pro': {
    label: () => __('WooCommerce Pro'),
    icon: ShoppingCart,
    order: 20,
    defaultExpanded: false,
    condition: 'hasWooCommercePro',
  },
  'two-way': {
    label: () => __('Two-Way SMS'),
    icon: ArrowLeftRight,
    order: 21,
    defaultExpanded: false,
    condition: 'hasTwoWay',
  },
}

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get list of all valid page IDs.
 * Used by SettingsContext for URL validation.
 */
export function getValidPageIds() {
  return Object.keys(pageDefinitions)
}

/**
 * Get page components map.
 * Used by AppShell for rendering pages.
 * Returns: { 'page-id': Component, ... }
 */
export function getPageComponents() {
  const components = {}
  for (const [id, page] of Object.entries(pageDefinitions)) {
    components[id] = page.component
  }
  return components
}

/**
 * Get navigation structure for Sidebar.
 * Builds the same structure that getNavigation() in Sidebar.jsx currently returns.
 */
export function getNavigation() {
  const navigation = []

  // 1. Add top-level items (type: 'item')
  const topLevelPages = Object.entries(pageDefinitions)
    .filter(([, page]) => page.nav.type === 'item')
    .sort((a, b) => a[1].nav.order - b[1].nav.order)

  for (const [id, page] of topLevelPages) {
    navigation.push({
      type: 'item',
      id,
      label: page.label(),
      icon: page.icon,
      condition: page.condition,
    })
  }

  // 2. Add groups with their items
  const sortedGroups = Object.entries(navGroups)
    .sort((a, b) => a[1].order - b[1].order)

  // Insert separator before add-on groups
  const hasAddons = sortedGroups.some(([, group]) =>
    group.condition === 'hasWooCommercePro' || group.condition === 'hasTwoWay'
  )
  if (hasAddons) {
    navigation.push({
      type: 'separator',
      label: __('ADD-ONS'),
      condition: 'hasAnyAddon',
    })
  }

  for (const [groupId, group] of sortedGroups) {
    // Get pages in this group
    const groupPages = Object.entries(pageDefinitions)
      .filter(([, page]) => page.nav.type === 'group-item' && page.nav.group === groupId)
      .sort((a, b) => a[1].nav.order - b[1].nav.order)

    if (groupPages.length === 0) continue

    navigation.push({
      type: 'group',
      id: `${groupId}-group`,
      label: group.label(),
      icon: group.icon,
      defaultExpanded: group.defaultExpanded,
      condition: group.condition,
      items: groupPages.map(([id, page]) => ({
        id,
        label: page.label(),
        icon: page.icon,
      })),
    })
  }

  return navigation
}

/**
 * Check if a page ID is valid.
 */
export function isValidPage(pageId) {
  return pageId in pageDefinitions
}

/**
 * Get a specific page definition.
 */
export function getPageDefinition(pageId) {
  return pageDefinitions[pageId]
}

// Export VALID_PAGES as array for backward compatibility
export const VALID_PAGES = getValidPageIds()
