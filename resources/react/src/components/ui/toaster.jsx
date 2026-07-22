import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react'
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

// Simple Toast Component (avoiding Radix UI portal issues)
function Toast({ id, title, description, variant, onDismiss }) {
  const [isExiting, setIsExiting] = useState(false)

  const handleDismiss = () => {
    setIsExiting(true)
    setTimeout(() => onDismiss(id), 150)
  }

  return (
    <div
      className={cn(
        'wsms-pointer-events-auto wsms-relative wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-gap-4 wsms-overflow-hidden wsms-rounded-md wsms-border wsms-p-4 wsms-pe-8 wsms-shadow-lg wsms-transition-all wsms-duration-150',
        isExiting
          ? 'wsms-opacity-0 wsms-translate-x-2 rtl:wsms--translate-x-2'
          : 'wsms-opacity-100 wsms-translate-x-0',
         variant === 'destructive' && 'wsms-border-red-200 wsms-bg-red-50 wsms-text-red-900 dark:wsms-border-red-800 dark:wsms-bg-red-950 dark:wsms-text-red-200',
         variant === 'success' && 'wsms-border-emerald-200 wsms-bg-emerald-50 wsms-text-emerald-900 dark:wsms-border-emerald-800 dark:wsms-bg-emerald-950 dark:wsms-text-emerald-200',
         variant === 'warning' && 'wsms-border-amber-200 wsms-bg-amber-50 wsms-text-amber-900 dark:wsms-border-amber-800 dark:wsms-bg-amber-950 dark:wsms-text-amber-200',
         variant === 'default' && 'wsms-border-border wsms-bg-popover wsms-text-popover-foreground'
      )}
      style={{ boxShadow: '0 4px 12px rgba(0,0,0,0.15)' }}
    >
      <div className="wsms-grid wsms-gap-1">
        {title && (
          <div className="wsms-text-sm wsms-font-semibold">{title}</div>
        )}
        {description && (
          <div className="wsms-text-sm wsms-opacity-90">{description}</div>
        )}
      </div>
      <button
        onClick={handleDismiss}
        className="wsms-absolute wsms-end-2 wsms-top-2 wsms-rounded-md wsms-p-1 wsms-text-current wsms-opacity-50 hover:wsms-opacity-100 wsms-transition-opacity"
      >
        <X className="wsms-h-4 wsms-w-4" />
      </button>
    </div>
  )
}

// Toast Provider - Simple implementation without Radix UI portals
export function Toaster({ children }) {
  const [toasts, setToasts] = useState([])
  const containerRef = useRef(null)

  const toast = useCallback(({ title, description, variant = 'default', duration = 5000 }) => {
    const id = Date.now()
    setToasts((prev) => [...prev, { id, title, description, variant }])

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
      {children}
      {/* Toast viewport */}
      <div
        ref={containerRef}
        className="wsms-fixed wsms-bottom-4 wsms-end-4 wsms-z-[9999999] wsms-flex wsms-flex-col wsms-gap-2 wsms-max-w-[380px] wsms-w-full wsms-pointer-events-none"
      >
        {toasts.map(({ id, title, description, variant }) => (
          <Toast
            key={id}
            id={id}
            title={title}
            description={description}
            variant={variant}
            onDismiss={dismiss}
          />
        ))}
      </div>
    </ToastContext.Provider>
  )
}
