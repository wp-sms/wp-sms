import * as React from 'react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { cva } from 'class-variance-authority'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

const Dialog = DialogPrimitive.Root

const DialogTrigger = DialogPrimitive.Trigger

const DialogPortal = DialogPrimitive.Portal

const DialogClose = DialogPrimitive.Close

const DialogOverlay = React.forwardRef(({ className, ...props }, ref) => (
  <DialogPrimitive.Overlay
    ref={ref}
    className={cn(
      'wsms-fixed wsms-inset-0 wsms-z-[99999] wsms-bg-black/60 wsms-backdrop-blur-sm',
      // Animation classes
      'data-[state=open]:wsms-animate-in data-[state=closed]:wsms-animate-out',
      'data-[state=closed]:wsms-fade-out-0 data-[state=open]:wsms-fade-in-0',
      className
    )}
    {...props}
  />
))
DialogOverlay.displayName = DialogPrimitive.Overlay.displayName

const dialogContentVariants = cva(
  [
    'wsms-fixed wsms-left-[50%] wsms-top-[50%] wsms-z-[99999] wsms-grid wsms-w-full',
    'wsms-translate-x-[-50%] wsms-translate-y-[-50%]',
    'wsms-bg-card wsms-shadow-2xl wsms-border wsms-border-border',
    // Animation
    'data-[state=open]:wsms-animate-in data-[state=closed]:wsms-animate-out',
    'data-[state=closed]:wsms-fade-out-0 data-[state=open]:wsms-fade-in-0',
    'data-[state=closed]:wsms-zoom-out-95 data-[state=open]:wsms-zoom-in-95',
    'data-[state=closed]:wsms-slide-out-to-left-1/2 data-[state=closed]:wsms-slide-out-to-top-[48%]',
    'data-[state=open]:wsms-slide-in-from-left-1/2 data-[state=open]:wsms-slide-in-from-top-[48%]',
    'wsms-duration-200',
  ].join(' '),
  {
    variants: {
      size: {
        sm: 'wsms-max-w-sm wsms-rounded-lg',
        default: 'wsms-max-w-lg wsms-rounded-lg',
        lg: 'wsms-max-w-2xl wsms-rounded-lg',
        xl: 'wsms-max-w-4xl wsms-rounded-xl',
        full: 'wsms-max-w-[calc(100vw-2rem)] wsms-max-h-[calc(100vh-2rem)] wsms-rounded-xl',
      },
    },
    defaultVariants: {
      size: 'default',
    },
  }
)

const DialogContent = React.forwardRef(
  ({ className, children, size, showClose = true, onInteractOutside, ...props }, ref) => (
    <DialogPortal>
      <DialogOverlay />
      <DialogPrimitive.Content
        ref={ref}
        className={cn(dialogContentVariants({ size }), className)}
        onInteractOutside={onInteractOutside}
        {...props}
      >
        {children}
        {showClose && (
          <DialogPrimitive.Close
            className={cn(
              'wsms-absolute wsms-right-4 wsms-top-4 wsms-rounded-md wsms-p-1.5',
              'wsms-text-muted-foreground/70 wsms-transition-all',
              'hover:wsms-bg-accent hover:wsms-text-foreground',
              'focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20',
              'disabled:wsms-pointer-events-none',
              'data-[state=open]:wsms-bg-accent'
            )}
          >
            <X className="wsms-h-4 wsms-w-4" />
            <span className="wsms-sr-only">Close</span>
          </DialogPrimitive.Close>
        )}
      </DialogPrimitive.Content>
    </DialogPortal>
  )
)
DialogContent.displayName = DialogPrimitive.Content.displayName

const DialogHeader = ({ className, ...props }) => (
  <div
    className={cn(
      'wsms-flex wsms-flex-col wsms-gap-1.5 wsms-p-5 wsms-pb-4',
      'wsms-border-b wsms-border-border',
      className
    )}
    {...props}
  />
)
DialogHeader.displayName = 'DialogHeader'

const DialogFooter = ({ className, ...props }) => (
  <div
    className={cn(
      'wsms-flex wsms-flex-col-reverse sm:wsms-flex-row sm:wsms-justify-end wsms-gap-2',
      'wsms-p-4 wsms-border-t wsms-border-border wsms-bg-muted/30',
      className
    )}
    {...props}
  />
)
DialogFooter.displayName = 'DialogFooter'

const DialogTitle = React.forwardRef(({ className, ...props }, ref) => (
  <DialogPrimitive.Title
    ref={ref}
    className={cn(
      'wsms-text-[15px] wsms-font-semibold wsms-leading-none wsms-tracking-tight wsms-text-foreground',
      className
    )}
    {...props}
  />
))
DialogTitle.displayName = DialogPrimitive.Title.displayName

const DialogDescription = React.forwardRef(({ className, ...props }, ref) => (
  <DialogPrimitive.Description
    ref={ref}
    className={cn('wsms-text-[13px] wsms-text-muted-foreground', className)}
    {...props}
  />
))
DialogDescription.displayName = DialogPrimitive.Description.displayName

const DialogBody = React.forwardRef(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      'wsms-p-5 wsms-overflow-y-auto wsms-max-h-[60vh]',
      // Custom scrollbar styling
      'wsms-scrollbar-thin wsms-scrollbar-thumb-border wsms-scrollbar-track-transparent',
      className
    )}
    {...props}
  />
))
DialogBody.displayName = 'DialogBody'

export {
  Dialog,
  DialogPortal,
  DialogOverlay,
  DialogTrigger,
  DialogClose,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
  DialogDescription,
  DialogBody,
}
