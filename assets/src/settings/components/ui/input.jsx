import * as React from 'react'
import PropTypes from 'prop-types'
import { cn } from '@/lib/utils'

const Input = React.forwardRef(({ className, type, ...props }, ref) => {
  return (
    <input
      type={type}
      className={cn(
        'wsms-flex wsms-h-9 wsms-w-full wsms-rounded-md wsms-border wsms-border-input wsms-bg-card wsms-px-3 wsms-text-[13px] wsms-text-foreground wsms-shadow-sm placeholder:wsms-text-muted-foreground hover:wsms-border-primary/50 focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20 focus:wsms-border-primary disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50 disabled:wsms-bg-muted',
        className
      )}
      ref={ref}
      {...props}
    />
  )
})
Input.displayName = 'Input'

Input.propTypes = {
  className: PropTypes.string,
  type: PropTypes.string,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
  onChange: PropTypes.func,
  placeholder: PropTypes.string,
  disabled: PropTypes.bool,
  'aria-invalid': PropTypes.bool,
}

export { Input }
