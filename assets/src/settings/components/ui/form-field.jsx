import * as React from 'react'
import PropTypes from 'prop-types'
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
 * FieldDescription - Renders field descriptions with proper HTML support
 * Safely renders <code> tags as styled code chips
 */
const FieldDescription = ({ children, className }) => {
  if (!children) return null

  // Parse HTML and convert <code> tags to styled spans
  const renderDescription = (text) => {
    if (typeof text !== 'string') return text

    // Split by <code> tags and render appropriately
    const parts = text.split(/(<code>.*?<\/code>)/g)

    return parts.map((part, index) => {
      // Check if this part is a <code> tag
      const codeMatch = part.match(/<code>(.*?)<\/code>/)
      if (codeMatch) {
        return (
          <code
            key={index}
            className="wsms-px-1.5 wsms-py-0.5 wsms-mx-0.5 wsms-rounded wsms-bg-muted wsms-font-mono wsms-text-[11px] wsms-text-primary wsms-whitespace-nowrap"
          >
            {codeMatch[1]}
          </code>
        )
      }
      return part
    })
  }

  return (
    <p className={cn('wsms-text-[12px] wsms-text-muted-foreground wsms-leading-relaxed', className)}>
      {renderDescription(children)}
    </p>
  )
}

FieldDescription.propTypes = {
  children: PropTypes.string,
  className: PropTypes.string,
}

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
          <FieldDescription className="wsms-mt-1">{description}</FieldDescription>
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
            <FieldDescription>{description}</FieldDescription>
          )}
        </div>
        <Switch
          checked={checked}
          onCheckedChange={onCheckedChange}
          disabled={disabled}
          aria-label={label}
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
          <FieldDescription>{description}</FieldDescription>
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
          <FieldDescription>{description}</FieldDescription>
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
          <FieldDescription>{description}</FieldDescription>
        )}
        {error && (
          <p className="wsms-text-[12px] wsms-text-destructive">{error}</p>
        )}
      </div>
    )
  }
)
SelectField.displayName = 'SelectField'

// PropTypes definitions
FormField.propTypes = {
  label: PropTypes.string,
  description: PropTypes.string,
  error: PropTypes.string,
  required: PropTypes.bool,
  className: PropTypes.string,
  children: PropTypes.element.isRequired,
}

SwitchField.propTypes = {
  label: PropTypes.string.isRequired,
  description: PropTypes.string,
  checked: PropTypes.bool,
  onCheckedChange: PropTypes.func.isRequired,
  disabled: PropTypes.bool,
  className: PropTypes.string,
}

InputField.propTypes = {
  label: PropTypes.string,
  description: PropTypes.string,
  error: PropTypes.string,
  required: PropTypes.bool,
  type: PropTypes.string,
  className: PropTypes.string,
  inputClassName: PropTypes.string,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  onChange: PropTypes.func,
  placeholder: PropTypes.string,
  disabled: PropTypes.bool,
}

TextareaField.propTypes = {
  label: PropTypes.string,
  description: PropTypes.string,
  error: PropTypes.string,
  required: PropTypes.bool,
  className: PropTypes.string,
  textareaClassName: PropTypes.string,
  value: PropTypes.string,
  onChange: PropTypes.func,
  placeholder: PropTypes.string,
  disabled: PropTypes.bool,
  rows: PropTypes.number,
}

const optionShape = PropTypes.shape({
  value: PropTypes.string.isRequired,
  label: PropTypes.string.isRequired,
  disabled: PropTypes.bool,
})

SelectField.propTypes = {
  label: PropTypes.string,
  description: PropTypes.string,
  error: PropTypes.string,
  required: PropTypes.bool,
  placeholder: PropTypes.string,
  value: PropTypes.string,
  onValueChange: PropTypes.func.isRequired,
  options: PropTypes.arrayOf(optionShape),
  className: PropTypes.string,
  disabled: PropTypes.bool,
}

export {
  FormField,
  SwitchField,
  InputField,
  TextareaField,
  SelectField,
  FieldDescription,
}
