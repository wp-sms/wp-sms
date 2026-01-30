import React from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Loading spinner for dialog bodies. Shows a centered spinner
 * while async data is being fetched for a dialog form.
 *
 * Usage: Conditionally render this instead of the dialog form content
 * when detail data is loading.
 */
export function DialogLoadingSpinner({ className }) {
  return (
    <div className={cn('wsms-flex wsms-items-center wsms-justify-center wsms-py-12', className)}>
      <Loader2 className="wsms-h-6 wsms-w-6 wsms-animate-spin wsms-text-muted-foreground" />
    </div>
  )
}
