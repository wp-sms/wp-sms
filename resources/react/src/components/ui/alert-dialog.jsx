import * as React from 'react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

const AlertDialog = ({ children, ...props }) => (
  <Dialog {...props}>{children}</Dialog>
)

const AlertDialogTrigger = ({ children, ...props }) => children

const AlertDialogContent = React.forwardRef(({ className, children, ...props }, ref) => (
  <DialogContent
    ref={ref}
    size="sm"
    className={cn(className)}
    showClose={false}
    onInteractOutside={(e) => e.preventDefault()}
    {...props}
  >
    {children}
  </DialogContent>
))
AlertDialogContent.displayName = 'AlertDialogContent'

const AlertDialogHeader = ({ className, ...props }) => (
  <DialogHeader className={cn(className)} {...props} />
)
AlertDialogHeader.displayName = 'AlertDialogHeader'

const AlertDialogFooter = ({ className, ...props }) => (
  <DialogFooter className={cn(className)} {...props} />
)
AlertDialogFooter.displayName = 'AlertDialogFooter'

const AlertDialogTitle = React.forwardRef(({ className, ...props }, ref) => (
  <DialogTitle ref={ref} className={cn(className)} {...props} />
))
AlertDialogTitle.displayName = 'AlertDialogTitle'

const AlertDialogDescription = React.forwardRef(({ className, ...props }, ref) => (
  <DialogDescription ref={ref} className={cn(className)} {...props} />
))
AlertDialogDescription.displayName = 'AlertDialogDescription'

const AlertDialogAction = React.forwardRef(({ className, ...props }, ref) => (
  <Button ref={ref} className={cn(className)} {...props} />
))
AlertDialogAction.displayName = 'AlertDialogAction'

const AlertDialogCancel = React.forwardRef(({ className, ...props }, ref) => (
  <Button ref={ref} variant="outline" className={cn(className)} {...props} />
))
AlertDialogCancel.displayName = 'AlertDialogCancel'

export {
  AlertDialog,
  AlertDialogTrigger,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogFooter,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogAction,
  AlertDialogCancel,
}
