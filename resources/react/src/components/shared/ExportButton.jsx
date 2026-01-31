import React, { useState } from 'react'
import { Download, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useToast } from '@/components/ui/toaster'
import { __ } from '@/lib/utils'

/**
 * Reusable Export Button with loading state
 *
 * @param {Object} props
 * @param {Function} props.onExport - Async function that performs the export. Should return { count } or throw on error.
 * @param {string} [props.label] - Button label (default: "Export")
 * @param {string} [props.successMessage] - Success toast message. Use %d for count placeholder.
 * @param {string} [props.errorMessage] - Error toast message (default: "Export failed")
 * @param {string} [props.variant] - Button variant (default: "outline")
 * @param {string} [props.size] - Button size
 * @param {boolean} [props.disabled] - Additional disabled state
 * @param {string} [props.className] - Additional CSS classes
 */
export function ExportButton({
  onExport,
  label,
  successMessage,
  errorMessage,
  variant = 'outline',
  size,
  disabled = false,
  className,
}) {
  const [isExporting, setIsExporting] = useState(false)
  const { toast } = useToast()

  const handleExport = async () => {
    if (isExporting || disabled) return

    setIsExporting(true)
    try {
      const result = await onExport()
      if (successMessage && result?.count !== undefined) {
        toast({
          title: successMessage.replace('%d', result.count),
          variant: 'success',
        })
      }
    } catch (error) {
      toast({
        title: error.message || errorMessage || __('Export failed'),
        variant: 'destructive',
      })
    } finally {
      setIsExporting(false)
    }
  }

  return (
    <Button
      variant={variant}
      size={size}
      onClick={handleExport}
      disabled={isExporting || disabled}
      className={className}
    >
      {isExporting ? (
        <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" aria-hidden="true" />
      ) : (
        <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
      )}
      {label || __('Export')}
    </Button>
  )
}
