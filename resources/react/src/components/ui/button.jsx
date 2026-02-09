import * as React from 'react'
import PropTypes from 'prop-types'
import { Slot } from '@radix-ui/react-slot'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

function flattenFragments(children) {
  const out = []
  for (const child of React.Children.toArray(children)) {
    if (React.isValidElement(child) && child.type === React.Fragment) {
      out.push(...flattenFragments(child.props.children))
      continue
    }
    out.push(child)
  }
  return out
}

function hasExplicitLabel(children) {
  for (const child of React.Children.toArray(children)) {
    if (!React.isValidElement(child)) continue
    const cls = child.props?.className
    if (typeof cls === 'string' && cls.split(/\s+/).includes('wsms-btn__label')) return true
  }
  return false
}

function normalizeButtonChildren(children) {
  if (children == null) return children
  if (hasExplicitLabel(children)) return children

  const flat = flattenFragments(children)
  const normalized = []
  let textBuf = ''

  const flush = () => {
    const txt = textBuf.trim()
    textBuf = ''
    if (!txt) return
    normalized.push(
      <span key={`wsms-btn__label-${normalized.length}`} className="wsms-btn__label">
        {txt}
      </span>
    )
  }

  for (const node of flat) {
    if (typeof node === 'string' || typeof node === 'number') {
      textBuf += String(node)
      continue
    }

    flush()
    normalized.push(node)
  }

  flush()
  return normalized
}

const buttonVariants = cva(
  'wsms-btn wsms-inline-flex wsms-items-center wsms-justify-center wsms-whitespace-nowrap wsms-rounded-md wsms-text-[13px] wsms-font-medium wsms-transition-all focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/20 disabled:wsms-pointer-events-none disabled:wsms-opacity-50',
  {
    variants: {
      variant: {
        default:
          'wsms-bg-primary wsms-text-primary-foreground wsms-shadow-sm hover:wsms-bg-primary/90 active:wsms-scale-[0.98]',
        destructive:
          'wsms-bg-destructive wsms-text-destructive-foreground wsms-shadow-sm hover:wsms-bg-destructive/90',
        outline:
          'wsms-border wsms-border-input wsms-bg-card wsms-shadow-sm hover:wsms-bg-accent/50 hover:wsms-border-primary/50',
        secondary:
          'wsms-bg-secondary wsms-text-secondary-foreground wsms-shadow-sm hover:wsms-bg-secondary/80',
        ghost:
          'hover:wsms-bg-accent',
        link:
          'wsms-text-primary wsms-underline-offset-4 hover:wsms-underline',
      },
      size: {
        default: 'wsms-h-9 wsms-px-4',
        sm: 'wsms-h-8 wsms-px-3 wsms-text-[12px]',
        lg: 'wsms-h-10 wsms-px-5',
        icon: 'wsms-h-9 wsms-w-9',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
)

const Button = React.forwardRef(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button'

    // Normalize: wrap plain text into a label span so we can apply consistent
    // physical icon placement in RTL via CSS ordering (without direction hacks).
    let children = props.children
    if (asChild && React.isValidElement(children)) {
      const normalized = normalizeButtonChildren(children.props?.children)
      children = React.cloneElement(children, children.props, normalized)
    } else {
      children = normalizeButtonChildren(children)
    }

    return (
      <Comp
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      >
        {children}
      </Comp>
    )
  }
)
Button.displayName = 'Button'

Button.propTypes = {
  className: PropTypes.string,
  variant: PropTypes.oneOf(['default', 'destructive', 'outline', 'secondary', 'ghost', 'link']),
  size: PropTypes.oneOf(['default', 'sm', 'lg', 'icon']),
  asChild: PropTypes.bool,
  children: PropTypes.node,
  disabled: PropTypes.bool,
  onClick: PropTypes.func,
  type: PropTypes.oneOf(['button', 'submit', 'reset']),
}

export { Button, buttonVariants }
