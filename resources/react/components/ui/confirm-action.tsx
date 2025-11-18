import { __ } from '@wordpress/i18n'
import type { PropsWithChildren } from 'react'

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from './alert-dialog'

export type ConfirmActionProps = PropsWithChildren<{
  onConfirm: () => void
}>

export const ConfirmAction = ({ children, onConfirm }: ConfirmActionProps) => {
  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>{children}</AlertDialogTrigger>

      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="!m-0">{__('Are you absolutely sure?', 'wp-sms')}</AlertDialogTitle>

          <AlertDialogDescription className="!mt-1">
            {__(
              'This action cannot be undone. This will permanently delete your account and remove your data from our servers.',
              'wp-sms'
            )}
          </AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogCancel>{__('Cancel', 'wp-sms')}</AlertDialogCancel>
          <AlertDialogAction onClick={onConfirm} className="bg-destructive hover:bg-destructive/90">
            {__('Confirm', 'wp-sms')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
