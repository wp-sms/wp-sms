import React, { createContext, useContext, useState, useCallback } from 'react'
import * as ToastPrimitive from '@radix-ui/react-toast'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

// Toast Context
const ToastContext = createContext(null)

export function useToast() {
  const context = useContext(ToastContext)
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider')
  }
  return context
}

// Toast Provider
export function Toaster({ children }) {
  const [toasts, setToasts] = useState([])

  const toast = useCallback(({ title, description, variant = 'default', duration = 5000 }) => {
    const id = Date.now()
    setToasts((prev) => [...prev, { id, title, description, variant, duration }])

    // Auto-remove after duration
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id))
    }, duration)

    return id
  }, [])

  const dismiss = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  return (
    <ToastContext.Provider value={{ toast, dismiss }}>
      <ToastPrimitive.Provider swipeDirection="right">
        {children}
        {toasts.map(({ id, title, description, variant }) => (
          <ToastPrimitive.Root
            key={id}
            className={cn(
              'wsms-group wsms-pointer-events-auto wsms-relative wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-space-x-4 wsms-overflow-hidden wsms-rounded-md wsms-border wsms-p-6 wsms-pr-8 wsms-shadow-lg wsms-transition-all',
              'data-[swipe=cancel]:wsms-translate-x-0 data-[swipe=end]:wsms-translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:wsms-translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:wsms-transition-none data-[state=open]:wsms-animate-in data-[state=closed]:wsms-animate-out data-[swipe=end]:wsms-animate-out data-[state=closed]:wsms-fade-out-80 data-[state=closed]:wsms-slide-out-to-right-full data-[state=open]:wsms-slide-in-from-top-full data-[state=open]:sm:wsms-slide-in-from-bottom-full',
              variant === 'destructive' && 'wsms-border-destructive wsms-bg-destructive wsms-text-destructive-foreground',
              variant === 'success' && 'wsms-border-success wsms-bg-success wsms-text-success-foreground',
              variant === 'default' && 'wsms-border wsms-bg-background wsms-text-foreground'
            )}
          >
            <div className="wsms-grid wsms-gap-1">
              {title && (
                <ToastPrimitive.Title className="wsms-text-sm wsms-font-semibold">
                  {title}
                </ToastPrimitive.Title>
              )}
              {description && (
                <ToastPrimitive.Description className="wsms-text-sm wsms-opacity-90">
                  {description}
                </ToastPrimitive.Description>
              )}
            </div>
            <ToastPrimitive.Close
              className="wsms-absolute wsms-right-2 wsms-top-2 wsms-rounded-md wsms-p-1 wsms-text-foreground/50 wsms-opacity-0 wsms-transition-opacity hover:wsms-text-foreground focus:wsms-opacity-100 focus:wsms-outline-none focus:wsms-ring-2 group-hover:wsms-opacity-100"
              onClick={() => dismiss(id)}
            >
              <X className="wsms-h-4 wsms-w-4" />
            </ToastPrimitive.Close>
          </ToastPrimitive.Root>
        ))}
        <ToastPrimitive.Viewport className="wsms-fixed wsms-bottom-0 wsms-right-0 wsms-z-[100] wsms-flex wsms-max-h-screen wsms-w-full wsms-flex-col-reverse wsms-p-4 sm:wsms-bottom-0 sm:wsms-right-0 sm:wsms-top-auto sm:wsms-flex-col md:wsms-max-w-[420px]" />
      </ToastPrimitive.Provider>
    </ToastContext.Provider>
  )
}
