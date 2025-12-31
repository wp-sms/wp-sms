import * as React from 'react'
import { cn } from '@/lib/utils'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

/**
 * FormField - A flexible form field wrapper component
 */
const FormField = React.forwardRef(
  ({ label, description, error, required, className, children, ...props }, ref) => {
    const id = React.useId()

    return (
      <div ref={ref} className={cn('wsms-space-y-2', className)} {...props}>
        {label && (
          <Label htmlFor={id} className={cn('wsms-text-[13px] wsms-font-medium', error && 'wsms-text-destructive')}>
            {label}
            {required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
          </Label>
        )}
        {React.cloneElement(children, { id, 'aria-invalid': !!error })}
        {description && !error && (
          <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-1">{description}</p>
        )}
        {error && (
          <p className="wsms-text-[12px] wsms-text-destructive wsms-mt-1">{error}</p>
        )}
      </div>
    )
  }
)
FormField.displayName = 'FormField'

/**
 * SwitchField - A toggle switch with label and description
 */
const SwitchField = React.forwardRef(
  ({ label, description, checked, onCheckedChange, disabled, className, ...props }, ref) => {
    return (
      <div
        ref={ref}
        className={cn(
          'wsms-flex wsms-items-center wsms-justify-between wsms-py-3',
          disabled && 'wsms-opacity-50',
          className
        )}
        {...props}
      >
        <div className="wsms-space-y-1 wsms-pr-4">
          <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{label}</p>
          {description && (
            <p className="wsms-text-[12px] wsms-text-muted-foreground">{description}</p>
          )}
        </div>
        <Switch
          checked={checked}
          onCheckedChange={onCheckedChange}
          disabled={disabled}
        />
      </div>
    )
  }
)
SwitchField.displayName = 'SwitchField'

/**
 * InputField - A text input with label, description, and error handling
 */
const InputField = React.forwardRef(
  ({ label, description, error, required, type = 'text', className, inputClassName, ...props }, ref) => {
    const id = React.useId()

    return (
      <div ref={ref} className={cn('wsms-space-y-2', className)}>
        {label && (
          <Label htmlFor={id} className={cn('wsms-text-[13px] wsms-font-medium', error && 'wsms-text-destructive')}>
            {label}
            {required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
          </Label>
        )}
        <Input
          id={id}
          type={type}
          aria-invalid={!!error}
          className={cn(error && 'wsms-border-destructive', inputClassName)}
          {...props}
        />
        {description && !error && (
          <p className="wsms-text-[12px] wsms-text-muted-foreground">{description}</p>
        )}
        {error && (
          <p className="wsms-text-[12px] wsms-text-destructive">{error}</p>
        )}
      </div>
    )
  }
)
InputField.displayName = 'InputField'

/**
 * TextareaField - A textarea with label, description, and error handling
 */
const TextareaField = React.forwardRef(
  ({ label, description, error, required, className, textareaClassName, ...props }, ref) => {
    const id = React.useId()

    return (
      <div ref={ref} className={cn('wsms-space-y-2', className)}>
        {label && (
          <Label htmlFor={id} className={cn('wsms-text-[13px] wsms-font-medium', error && 'wsms-text-destructive')}>
            {label}
            {required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
          </Label>
        )}
        <Textarea
          id={id}
          aria-invalid={!!error}
          className={cn(error && 'wsms-border-destructive', textareaClassName)}
          {...props}
        />
        {description && !error && (
          <p className="wsms-text-[12px] wsms-text-muted-foreground">{description}</p>
        )}
        {error && (
          <p className="wsms-text-[12px] wsms-text-destructive">{error}</p>
        )}
      </div>
    )
  }
)
TextareaField.displayName = 'TextareaField'

/**
 * SelectField - A select dropdown with label, description, and error handling
 */
const SelectField = React.forwardRef(
  ({ label, description, error, required, placeholder, value, onValueChange, options = [], className, ...props }, ref) => {
    const id = React.useId()

    return (
      <div ref={ref} className={cn('wsms-space-y-2', className)}>
        {label && (
          <Label htmlFor={id} className={cn('wsms-text-[13px] wsms-font-medium', error && 'wsms-text-destructive')}>
            {label}
            {required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
          </Label>
        )}
        <Select value={value} onValueChange={onValueChange} {...props}>
          <SelectTrigger id={id} className={cn(error && 'wsms-border-destructive')}>
            <SelectValue placeholder={placeholder} />
          </SelectTrigger>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.value} value={option.value} disabled={option.disabled}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {description && !error && (
          <p className="wsms-text-[12px] wsms-text-muted-foreground">{description}</p>
        )}
        {error && (
          <p className="wsms-text-[12px] wsms-text-destructive">{error}</p>
        )}
      </div>
    )
  }
)
SelectField.displayName = 'SelectField'

export {
  FormField,
  SwitchField,
  InputField,
  TextareaField,
  SelectField,
}
