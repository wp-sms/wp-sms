import * as React from 'react'
import { Plus, Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

/**
 * QuickAddForm - Inline form for quickly adding new items
 * Used for adding groups, subscribers, etc. without opening a modal
 */
const QuickAddForm = React.forwardRef(
  (
    {
      className,
      placeholder = 'Enter name...',
      buttonLabel = 'Add',
      onSubmit,
      isLoading = false,
      disabled = false,
      maxLength,
      validate,
      ...props
    },
    ref
  ) => {
    const [value, setValue] = React.useState('')
    const [error, setError] = React.useState('')
    const inputRef = React.useRef(null)

    const handleSubmit = async (e) => {
      e.preventDefault()

      const trimmedValue = value.trim()
      if (!trimmedValue) return

      // Custom validation
      if (validate) {
        const validationError = validate(trimmedValue)
        if (validationError) {
          setError(validationError)
          return
        }
      }

      setError('')

      try {
        await onSubmit?.(trimmedValue)
        setValue('')
        inputRef.current?.focus()
      } catch (err) {
        setError(err.message || 'Failed to add item')
      }
    }

    const handleChange = (e) => {
      setValue(e.target.value)
      if (error) setError('')
    }

    return (
      <form
        ref={ref}
        onSubmit={handleSubmit}
        className={cn('wsms-flex wsms-gap-2 wsms-items-start', className)}
        {...props}
      >
        <div className="wsms-flex-1 wsms-space-y-1">
          <Input
            ref={inputRef}
            type="text"
            value={value}
            onChange={handleChange}
            placeholder={placeholder}
            disabled={isLoading || disabled}
            maxLength={maxLength}
            className={cn(
              error && 'wsms-border-red-500 focus:wsms-ring-red-500/20'
            )}
          />
          {error && (
            <p className="wsms-text-[11px] wsms-text-red-500">{error}</p>
          )}
        </div>
        <Button
          type="submit"
          size="default"
          disabled={isLoading || disabled || !value.trim()}
          className="wsms-shrink-0"
        >
          {isLoading ? (
            <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
          ) : (
            <>
              <Plus className="wsms-h-4 wsms-w-4 wsms-mr-1" />
              {buttonLabel}
            </>
          )}
        </Button>
      </form>
    )
  }
)
QuickAddForm.displayName = 'QuickAddForm'

export { QuickAddForm }
