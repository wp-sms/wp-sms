import React from 'react'
import { Clock, Image, Eye, Edit, MessageSquare, Send, Trash2, RefreshCw, UserCheck, UserX, Repeat } from 'lucide-react'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { formatDate, __, cn } from '@/lib/utils'

/**
 * Factory function to create a date column
 * @param {Object} options - Column options
 * @param {string} options.id - Column ID
 * @param {string} options.accessorKey - Data accessor key
 * @param {string} options.header - Column header text
 * @param {boolean} options.showTime - Whether to show time
 * @returns {Object} Column definition
 */
export function createDateColumn({
  id = 'date',
  accessorKey = 'date',
  header = __('Date'),
  showTime = true,
} = {}) {
  return {
    id,
    accessorKey,
    header,
    sortable: true,
    cell: ({ row }) => {
      const value = row[accessorKey]
      if (!value) {
        return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
      }
      return (
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Clock className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" aria-hidden="true" />
          <span className="wsms-text-[12px] wsms-text-muted-foreground">
            {formatDate(value, showTime ? { hour: '2-digit', minute: '2-digit' } : {})}
          </span>
        </div>
      )
    },
  }
}

/**
 * Factory function to create a status column
 * @param {Object} options - Column options
 * @param {string} options.id - Column ID
 * @param {string} options.accessorKey - Data accessor key
 * @param {string} options.header - Column header text
 * @param {Object} options.statusMap - Map of status values to badge variants
 * @returns {Object} Column definition
 */
export function createStatusColumn({
  id = 'status',
  accessorKey = 'status',
  header = __('Status'),
  statusMap = {
    success: { variant: 'success', label: __('Sent') },
    failed: { variant: 'failed', label: __('Failed') },
    pending: { variant: 'warning', label: __('Pending') },
    active: { variant: 'success', label: __('Active') },
    inactive: { variant: 'default', label: __('Inactive') },
  },
} = {}) {
  return {
    id,
    accessorKey,
    header,
    cell: ({ row }) => {
      const status = row[accessorKey]
      const config = statusMap[status] || { variant: 'default', label: status }
      return <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
    },
  }
}

/**
 * Factory function to create a text column with optional truncation
 * @param {Object} options - Column options
 * @returns {Object} Column definition
 */
export function createTextColumn({
  id,
  accessorKey,
  header,
  maxWidth = 'md',
  lineClamp = 2,
} = {}) {
  return {
    id,
    accessorKey,
    header,
    cell: ({ row }) => (
      <p className={`wsms-text-[12px] wsms-text-foreground wsms-line-clamp-${lineClamp} wsms-max-w-${maxWidth}`}>
        {row[accessorKey]}
      </p>
    ),
  }
}

/**
 * Factory function to create a media column for MMS
 * @param {Object} options - Column options
 * @returns {Object} Column definition
 */
export function createMediaColumn({
  id = 'media',
  accessorKey = 'media',
  header = __('Media'),
  maxVisible = 2,
} = {}) {
  return {
    id,
    accessorKey,
    header,
    cell: ({ row }) => {
      const media = row[accessorKey]
      if (!media || (Array.isArray(media) && media.length === 0)) {
        return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
      }
      // Handle both array and string formats
      const mediaUrls = Array.isArray(media)
        ? media.filter(Boolean)
        : typeof media === 'string'
          ? media.split(',').map((url) => url.trim()).filter(Boolean)
          : []

      if (mediaUrls.length === 0) {
        return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
      }

      return (
        <div className="wsms-flex wsms-items-center wsms-gap-1">
          {mediaUrls.slice(0, maxVisible).map((url, idx) => (
            <a
              key={idx}
              href={url}
              target="_blank"
              rel="noopener noreferrer"
              className="wsms-flex wsms-items-center wsms-gap-1 wsms-px-2 wsms-py-1 wsms-rounded wsms-bg-muted/50 wsms-text-[11px] wsms-text-primary hover:wsms-bg-muted"
              aria-label={`View media ${idx + 1}`}
            >
              <Image className="wsms-h-3 wsms-w-3" aria-hidden="true" />
            </a>
          ))}
          {mediaUrls.length > maxVisible && (
            <span className="wsms-text-[11px] wsms-text-muted-foreground">
              +{mediaUrls.length - maxVisible}
            </span>
          )}
        </div>
      )
    },
  }
}

// ============================================
// Outbox-specific columns
// ============================================

/**
 * Get outbox table columns
 * These are defined outside the component to prevent recreation on every render
 */
export const outboxColumns = [
  createDateColumn({ showTime: true }),
  {
    id: 'recipient',
    accessorKey: 'recipient',
    header: __('Recipient'),
    cell: ({ row }) => {
      // Show "-" for empty or invalid recipients
      const hasRecipient = row.recipient && row.recipient.trim() !== ''
      const displayRecipient = hasRecipient
        ? (row.recipient_count > 1 ? `${row.recipient_count} ${__('recipients')}` : row.recipient)
        : '—'

      return (
        <div className="wsms-space-y-0.5">
          <span className={cn(
            'wsms-text-[13px] wsms-font-medium',
            hasRecipient ? 'wsms-text-foreground' : 'wsms-text-muted-foreground'
          )}>
            {displayRecipient}
          </span>
          {row.sender && (
            <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('From:')} {row.sender}</p>
          )}
        </div>
      )
    },
  },
  createTextColumn({ id: 'message', accessorKey: 'message', header: __('Message') }),
  createMediaColumn(),
  createStatusColumn({
    statusMap: {
      success: { variant: 'success', label: __('Sent') },
      failed: { variant: 'failed', label: __('Failed') },
      error: { variant: 'failed', label: __('Failed') },
    },
  }),
]

/**
 * Get outbox row actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Row actions
 */
export function getOutboxRowActions({ onView, onQuickReply, onResend, onDelete, isResending }) {
  return [
    {
      label: __('View Details'),
      icon: Eye,
      onClick: onView,
    },
    {
      label: __('Quick Reply'),
      icon: MessageSquare,
      onClick: onQuickReply,
      // Hide Quick Reply for failed messages or messages with no valid recipient
      hidden: (row) => row.status === 'failed' || !row.recipient || row.recipient.trim() === '',
    },
    {
      label: __('Resend'),
      icon: Send,
      onClick: onResend,
      loading: isResending,
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}

/**
 * Get outbox bulk actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Bulk actions
 */
export function getOutboxBulkActions({ onDelete, onResend }) {
  return [
    {
      label: __('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
    {
      label: __('Resend Selected'),
      icon: RefreshCw,
      onClick: onResend,
    },
  ]
}

// ============================================
// Subscribers-specific columns
// ============================================

/**
 * Get subscriber table columns
 * @param {Object} options - Column options
 * @param {Array} options.groups - Available groups for display
 * @param {Object} options.countries - Countries map for flag display
 * @returns {Array} Column definitions
 */
export function getSubscriberColumns({ groups = [], countries = {} } = {}) {
  return [
    {
      id: 'mobile',
      accessorKey: 'mobile',
      header: __('Phone Number'),
      cell: ({ row }) => (
        <div className="wsms-space-y-0.5">
          <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground wsms-font-mono">
            {row.mobile}
          </span>
          {row.name && (
            <p className="wsms-text-[11px] wsms-text-muted-foreground">{row.name}</p>
          )}
        </div>
      ),
    },
    {
      id: 'group',
      accessorKey: 'group_id',
      header: __('Group'),
      cell: ({ row }) => {
        const group = groups.find((g) => String(g.id) === String(row.group_id))
        return (
          <span className="wsms-text-[12px] wsms-text-muted-foreground">
            {group?.name || __('No group')}
          </span>
        )
      },
    },
    {
      id: 'country',
      accessorKey: 'country_code',
      header: __('Country'),
      cell: ({ row }) => {
        const country = countries[row.country_code]
        if (!country) {
          return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
        }
        return (
          <span className="wsms-text-[12px] wsms-text-muted-foreground">
            {country.flag} {country.name}
          </span>
        )
      },
    },
    createStatusColumn({
      statusMap: {
        '1': { variant: 'success', label: __('Active') },
        '0': { variant: 'default', label: __('Inactive') },
        active: { variant: 'success', label: __('Active') },
        inactive: { variant: 'default', label: __('Inactive') },
      },
    }),
    createDateColumn({
      id: 'created_at',
      accessorKey: 'created_at',
      header: __('Subscribed'),
      showTime: false,
    }),
  ]
}

/**
 * Get subscriber row actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Row actions
 */
export function getSubscriberRowActions({ onEdit, onQuickReply, onToggleStatus, onDelete }) {
  return [
    {
      label: __('Edit'),
      icon: Edit,
      onClick: onEdit,
    },
    {
      label: __('Send SMS'),
      icon: MessageSquare,
      onClick: onQuickReply,
    },
    {
      label: __('Toggle Status'),
      icon: UserCheck,
      onClick: onToggleStatus,
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}

/**
 * Get subscriber bulk actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Bulk actions
 */
export function getSubscriberBulkActions({ onDelete, onActivate, onDeactivate, onMoveToGroup }) {
  return [
    {
      label: __('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
    {
      label: __('Activate'),
      icon: UserCheck,
      onClick: onActivate,
    },
    {
      label: __('Deactivate'),
      icon: UserX,
      onClick: onDeactivate,
    },
  ]
}

// ============================================
// Scheduled SMS columns
// ============================================

/**
 * Get scheduled SMS table columns
 */
export const scheduledSmsColumns = [
  createDateColumn({
    id: 'date',
    accessorKey: 'date',
    header: __('Scheduled Date'),
    showTime: true,
  }),
  {
    id: 'recipient',
    accessorKey: 'recipient',
    header: __('Recipient'),
    cell: ({ row }) => (
      <div className="wsms-space-y-0.5">
        <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
          {row.recipient_count > 1 ? `${row.recipient_count} ${__('recipients')}` : row.recipient}
        </span>
        {row.sender && (
          <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('From:')} {row.sender}</p>
        )}
      </div>
    ),
  },
  createTextColumn({ id: 'message', accessorKey: 'message', header: __('Message') }),
  createMediaColumn(),
  createStatusColumn({
    statusMap: {
      pending: { variant: 'warning', label: __('Pending') },
      sent: { variant: 'success', label: __('Sent') },
      failed: { variant: 'failed', label: __('Failed') },
    },
  }),
]

/**
 * Get scheduled SMS row actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Row actions
 */
export function getScheduledRowActions({ onView, onEdit, onSendNow, onDelete }) {
  return [
    {
      label: __('View Details'),
      icon: Eye,
      onClick: onView,
    },
    {
      label: __('Edit'),
      icon: Edit,
      onClick: onEdit,
      condition: (row) => row.status === 'pending',
    },
    {
      label: __('Send Now'),
      icon: Send,
      onClick: onSendNow,
      condition: (row) => row.status === 'pending',
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}

/**
 * Get scheduled SMS bulk actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Bulk actions
 */
export function getScheduledBulkActions({ onDelete, onSendAll }) {
  return [
    {
      label: __('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
    {
      label: __('Send Selected Now'),
      icon: Send,
      onClick: onSendAll,
    },
  ]
}

// ============================================
// Repeating Messages columns
// ============================================

/**
 * Get repeating messages table columns
 */
export const repeatingMessagesColumns = [
  {
    id: 'interval',
    accessorKey: 'interval',
    header: __('Interval'),
    cell: ({ row }) => {
      const unitLabels = {
        minute: row.interval === 1 ? __('minute') : __('minutes'),
        hour: row.interval === 1 ? __('hour') : __('hours'),
        day: row.interval === 1 ? __('day') : __('days'),
        week: row.interval === 1 ? __('week') : __('weeks'),
        month: row.interval === 1 ? __('month') : __('months'),
      }
      return (
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Repeat className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" aria-hidden="true" />
          <span className="wsms-text-[12px] wsms-text-foreground">
            {__('Every')} {row.interval} {unitLabels[row.interval_unit] || row.interval_unit}
          </span>
        </div>
      )
    },
  },
  createDateColumn({
    id: 'next_occurrence',
    accessorKey: 'next_occurrence',
    header: __('Next Occurrence'),
    showTime: true,
  }),
  {
    id: 'ends_at',
    accessorKey: 'ends_at_date',
    header: __('Ends At'),
    cell: ({ row }) => (
      <span className="wsms-text-[12px] wsms-text-muted-foreground">
        {row.ends_at_date ? formatDate(row.ends_at_date, true) : __('Never')}
      </span>
    ),
  },
  {
    id: 'recipient',
    accessorKey: 'recipient',
    header: __('Recipient'),
    cell: ({ row }) => (
      <div className="wsms-space-y-0.5">
        <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
          {row.recipient_count > 1 ? `${row.recipient_count} ${__('recipients')}` : row.recipient}
        </span>
        {row.sender && (
          <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('From:')} {row.sender}</p>
        )}
      </div>
    ),
  },
  createTextColumn({ id: 'message', accessorKey: 'message', header: __('Message') }),
  {
    id: 'occurrences',
    accessorKey: 'occurrences_sent',
    header: __('Sent'),
    cell: ({ row }) => (
      <span className="wsms-text-[12px] wsms-text-muted-foreground">
        {row.occurrences_sent || 0}
        {row.max_occurrences ? ` / ${row.max_occurrences}` : ''}
      </span>
    ),
  },
  createStatusColumn({
    statusMap: {
      active: { variant: 'success', label: __('Active') },
      ended: { variant: 'default', label: __('Ended') },
    },
  }),
]

/**
 * Get repeating messages row actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Row actions
 */
export function getRepeatingRowActions({ onView, onEdit, onDelete }) {
  return [
    {
      label: __('View Details'),
      icon: Eye,
      onClick: onView,
    },
    {
      label: __('Edit'),
      icon: Edit,
      onClick: onEdit,
      condition: (row) => row.status === 'active',
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}

/**
 * Get repeating messages bulk actions
 * @param {Object} handlers - Action handlers
 * @returns {Array} Bulk actions
 */
export function getRepeatingBulkActions({ onDelete }) {
  return [
    {
      label: __('Delete Selected'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}
