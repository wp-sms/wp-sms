import React from 'react'
import { Loader2, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
} from '@/components/ui/dialog'
import { __ } from '@/lib/utils'

/**
 * Reusable delete confirmation dialog
 *
 * @param {Object} props
 * @param {boolean} props.isOpen - Whether the dialog is open
 * @param {Function} props.onClose - Callback to close the dialog
 * @param {Function} props.onConfirm - Callback when delete is confirmed
 * @param {boolean} props.isSaving - Whether deletion is in progress
 * @param {string} props.title - Dialog title (e.g., "Delete Group")
 * @param {string} props.description - Optional description text
 * @param {string} props.confirmLabel - Optional custom label for delete button (defaults to "Delete")
 * @param {React.ReactNode} props.children - Optional custom content for item preview
 */
export function DeleteConfirmDialog({
  isOpen,
  onClose,
  onConfirm,
  isSaving = false,
  title,
  description,
  confirmLabel,
  children,
}) {
  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent size="sm">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description && <DialogDescription>{description}</DialogDescription>}
        </DialogHeader>
        {children && <DialogBody>{children}</DialogBody>}
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            {__('Cancel')}
          </Button>
          <Button variant="destructive" onClick={onConfirm} disabled={isSaving}>
            {isSaving ? (
              <>
                <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                {__('Deleting...')}
              </>
            ) : (
              <>
                <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {confirmLabel || __('Delete')}
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default DeleteConfirmDialog
