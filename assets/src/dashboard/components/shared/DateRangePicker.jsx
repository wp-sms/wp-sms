import React from 'react'
import { CalendarDays } from 'lucide-react'
import { cn, __ } from '@/lib/utils'
import { Input } from '@/components/ui/input'

/**
 * DateRangePicker - A simple date range picker component
 *
 * @param {Object} props
 * @param {string} props.from - Start date value (YYYY-MM-DD format)
 * @param {string} props.to - End date value (YYYY-MM-DD format)
 * @param {Function} props.onFromChange - Callback when start date changes
 * @param {Function} props.onToChange - Callback when end date changes
 * @param {string} [props.className] - Additional CSS classes
 * @param {boolean} [props.disabled] - Disable the inputs
 */
export function DateRangePicker({
  from = '',
  to = '',
  onFromChange,
  onToChange,
  className,
  disabled = false,
}) {
  return (
    <div
      className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-pl-3 wsms-border-l wsms-border-border',
        disabled && 'wsms-opacity-50 wsms-pointer-events-none',
        className
      )}
    >
      <CalendarDays className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" aria-hidden="true" />
      <Input
        type="date"
        value={from}
        onChange={(e) => onFromChange?.(e.target.value)}
        disabled={disabled}
        className="wsms-h-9 wsms-w-[130px] wsms-text-[12px]"
        aria-label={__('From date')}
      />
      <span className="wsms-text-muted-foreground">—</span>
      <Input
        type="date"
        value={to}
        onChange={(e) => onToChange?.(e.target.value)}
        disabled={disabled}
        className="wsms-h-9 wsms-w-[130px] wsms-text-[12px]"
        aria-label={__('To date')}
      />
    </div>
  )
}
