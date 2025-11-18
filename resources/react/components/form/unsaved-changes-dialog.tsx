import { __ } from '@wordpress/i18n'

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

type UnsavedChangesDialogProps = {
  open: boolean
  onStay: () => void
  onDiscard: () => void
}

export const UnsavedChangesDialog = ({ open, onStay, onDiscard }: UnsavedChangesDialogProps) => {
  return (
    <AlertDialog open={open}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{__('Leave without saving?', 'wp-sms')}</AlertDialogTitle>
          <AlertDialogDescription>
            {__(
              'You have unsaved changes on this page. If you leave now, your changes will be lost.',
              'wp-sms'
            )}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel onClick={onDiscard}>{__('Discard and leave', 'wp-sms')}</AlertDialogCancel>
          <AlertDialogAction onClick={onStay}>{__('Stay on page', 'wp-sms')}</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
