import * as React from 'react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

const Dialog = DialogPrimitive.Root

const DialogTrigger = DialogPrimitive.Trigger

const DialogClose = DialogPrimitive.Close

// Size configurations
const sizeConfig = {
  sm: { maxWidth: '400px' },
  default: { maxWidth: '500px' },
  lg: { maxWidth: '640px' },
  xl: { maxWidth: '800px' },
  full: { maxWidth: 'calc(100vw - 2rem)', maxHeight: 'calc(100vh - 2rem)' },
}

const DialogContent = React.forwardRef(
  ({ className, children, size = 'default', showClose = true, onInteractOutside, style, ...props }, ref) => (
    <DialogPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
      {/* Overlay */}
      <DialogPrimitive.Overlay
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.5)',
          zIndex: 999999,
        }}
      />
      {/* Content */}
      <DialogPrimitive.Content
        ref={ref}
        className={cn('wsms-dialog-content', className)}
        onInteractOutside={onInteractOutside}
        style={{
          position: 'fixed',
          top: '50%',
          left: '50%',
          transform: 'translate(-50%, -50%)',
          zIndex: 999999,
          backgroundColor: '#ffffff',
          borderRadius: '12px',
          boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
          width: '90%',
          fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          fontSize: '14px',
          color: '#1f2937',
          overflow: 'hidden',
          ...sizeConfig[size],
          ...style,
        }}
        {...props}
      >
        {children}
        {showClose && (
          <DialogPrimitive.Close
            aria-label="Close dialog"
            style={{
              position: 'absolute',
              right: '12px',
              top: '12px',
              padding: '8px',
              borderRadius: '6px',
              border: 'none',
              background: 'transparent',
              cursor: 'pointer',
              color: '#9ca3af',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              transition: 'all 0.15s ease',
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.backgroundColor = '#f3f4f6'
              e.currentTarget.style.color = '#374151'
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.backgroundColor = 'transparent'
              e.currentTarget.style.color = '#9ca3af'
            }}
          >
            <X style={{ width: '18px', height: '18px' }} aria-hidden="true" />
          </DialogPrimitive.Close>
        )}
      </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
  )
)
DialogContent.displayName = DialogPrimitive.Content.displayName

const DialogHeader = ({ className, style, ...props }) => (
  <div
    className={cn('wsms-dialog-header', className)}
    style={{
      padding: '24px 24px 0 24px',
      ...style,
    }}
    {...props}
  />
)
DialogHeader.displayName = 'DialogHeader'

const DialogFooter = ({ className, style, ...props }) => (
  <div
    className={cn('wsms-dialog-footer', className)}
    style={{
      display: 'flex',
      justifyContent: 'flex-end',
      gap: '12px',
      padding: '20px 24px 24px 24px',
      ...style,
    }}
    {...props}
  />
)
DialogFooter.displayName = 'DialogFooter'

const DialogTitle = React.forwardRef(({ className, style, ...props }, ref) => (
  <DialogPrimitive.Title
    ref={ref}
    className={cn('wsms-dialog-title', className)}
    style={{
      fontSize: '16px',
      fontWeight: 600,
      lineHeight: 1.4,
      color: '#111827',
      margin: 0,
      ...style,
    }}
    {...props}
  />
))
DialogTitle.displayName = DialogPrimitive.Title.displayName

const DialogDescription = React.forwardRef(({ className, style, ...props }, ref) => (
  <DialogPrimitive.Description
    ref={ref}
    className={cn('wsms-dialog-description', className)}
    style={{
      fontSize: '13px',
      color: '#6b7280',
      margin: '4px 0 0 0',
      ...style,
    }}
    {...props}
  />
))
DialogDescription.displayName = DialogPrimitive.Description.displayName

const DialogBody = React.forwardRef(({ className, style, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('wsms-dialog-body', className)}
    style={{
      padding: '20px 24px',
      overflowY: 'auto',
      maxHeight: '60vh',
      ...style,
    }}
    {...props}
  />
))
DialogBody.displayName = 'DialogBody'

// Keep for backwards compatibility
const DialogPortal = DialogPrimitive.Portal
const DialogOverlay = DialogPrimitive.Overlay

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
