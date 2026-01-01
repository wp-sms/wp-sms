import * as React from 'react'
import { Check, ChevronDown, X, Search } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from './badge'
import { Button } from './button'
import { Input } from './input'

/**
 * MultiSelect component for selecting multiple items from a list
 *
 * @param {Object} props
 * @param {Array} props.options - Array of {value, label} objects or {key: label} object
 * @param {Array} props.value - Array of selected values
 * @param {Function} props.onValueChange - Callback when selection changes
 * @param {string} props.placeholder - Placeholder text when nothing selected
 * @param {string} props.searchPlaceholder - Placeholder for search input
 * @param {string} props.className - Additional CSS classes
 * @param {boolean} props.disabled - Whether the component is disabled
 */
const MultiSelect = React.forwardRef(
  (
    {
      options = [],
      value = [],
      onValueChange,
      placeholder = 'Select items...',
      searchPlaceholder = 'Search...',
      className,
      disabled = false,
      maxDisplayItems = 3,
      'aria-label': ariaLabel,
    },
    ref
  ) => {
    const [open, setOpen] = React.useState(false)
    const [search, setSearch] = React.useState('')
    const containerRef = React.useRef(null)

    // Close on click outside
    React.useEffect(() => {
      if (!open) return

      const handleClickOutside = (event) => {
        if (containerRef.current && !containerRef.current.contains(event.target)) {
          setOpen(false)
        }
      }

      document.addEventListener('mousedown', handleClickOutside)
      return () => document.removeEventListener('mousedown', handleClickOutside)
    }, [open])

    // Normalize options to array format
    const normalizedOptions = React.useMemo(() => {
      if (Array.isArray(options)) {
        // Check if it's an array of objects with value/label or code/name (like countries)
        if (options.length > 0 && typeof options[0] === 'object') {
          // If items already have value/label, use as-is
          if ('value' in options[0] && 'label' in options[0]) {
            return options
          }
          // Handle countries format: array of objects with code and name
          if ('code' in options[0] && 'name' in options[0]) {
            return options.map((item) => ({
              value: item.code,
              label: item.name,
            }))
          }
        }
        return options
      }
      // Convert object {key: label} to array format
      // Handle complex objects (like countries) that have a 'name' property
      return Object.entries(options).map(([key, labelOrObj]) => ({
        value: key,
        label: typeof labelOrObj === 'string'
          ? labelOrObj
          : (labelOrObj && typeof labelOrObj === 'object' && labelOrObj.name)
            ? labelOrObj.name
            : key,
      }))
    }, [options])

    // Ensure value is always an array
    const selectedValues = React.useMemo(() => {
      if (!value) return []
      if (Array.isArray(value)) return value
      return []
    }, [value])

    // Filter options based on search
    const filteredOptions = React.useMemo(() => {
      if (!search) return normalizedOptions
      const searchLower = search.toLowerCase()
      return normalizedOptions.filter(
        (option) =>
          option.label.toLowerCase().includes(searchLower) ||
          option.value.toLowerCase().includes(searchLower)
      )
    }, [normalizedOptions, search])

    // Get labels for selected values
    const selectedLabels = React.useMemo(() => {
      return selectedValues
        .map((val) => {
          const option = normalizedOptions.find((o) => o.value === val)
          return option ? option.label : val
        })
        .filter(Boolean)
    }, [selectedValues, normalizedOptions])

    const handleSelect = (optionValue) => {
      const newValue = selectedValues.includes(optionValue)
        ? selectedValues.filter((v) => v !== optionValue)
        : [...selectedValues, optionValue]
      onValueChange?.(newValue)
    }

    const handleRemove = (optionValue, e) => {
      e.stopPropagation()
      onValueChange?.(selectedValues.filter((v) => v !== optionValue))
    }

    const handleClearAll = (e) => {
      e.stopPropagation()
      onValueChange?.([])
    }

    return (
      <div
        ref={containerRef}
        className={cn('wsms-relative wsms-w-full', className)}
      >
        <Button
          ref={ref}
          variant="outline"
          role="combobox"
          type="button"
          aria-expanded={open}
          aria-label={ariaLabel || placeholder}
          disabled={disabled}
          onClick={() => !disabled && setOpen(!open)}
          className={cn(
            'wsms-w-full wsms-justify-between wsms-h-auto wsms-min-h-[36px] wsms-py-1.5 wsms-px-3 wsms-font-normal',
            !selectedValues.length && 'wsms-text-muted-foreground'
          )}
        >
          <div className="wsms-flex wsms-flex-wrap wsms-gap-1 wsms-flex-1 wsms-text-left">
            {selectedValues.length === 0 ? (
              <span>{placeholder}</span>
            ) : selectedValues.length <= maxDisplayItems ? (
              selectedLabels.map((label, index) => (
                <Badge
                  key={selectedValues[index]}
                  variant="secondary"
                  className="wsms-text-[11px] wsms-px-1.5 wsms-py-0"
                >
                  {label}
                  <button
                    type="button"
                    className="wsms-ml-1 wsms-rounded-full wsms-outline-none hover:wsms-bg-secondary-foreground/20"
                    onClick={(e) => handleRemove(selectedValues[index], e)}
                    aria-label={`Remove ${label}`}
                  >
                    <X className="wsms-h-3 wsms-w-3" />
                  </button>
                </Badge>
              ))
            ) : (
              <Badge variant="secondary" className="wsms-text-[11px] wsms-px-1.5 wsms-py-0">
                {selectedValues.length} selected
              </Badge>
            )}
          </div>
          <div className="wsms-flex wsms-items-center wsms-gap-1 wsms-ml-2">
            {selectedValues.length > 0 && (
              <button
                type="button"
                className="wsms-rounded-full wsms-p-0.5 hover:wsms-bg-accent"
                onClick={handleClearAll}
                aria-label="Clear all selections"
              >
                <X className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
              </button>
            )}
            <ChevronDown
              className={cn(
                'wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-transition-transform',
                open && 'wsms-rotate-180'
              )}
            />
          </div>
        </Button>

        {open && (
          <div className="wsms-absolute wsms-z-[9999] wsms-mt-1 wsms-w-full wsms-rounded wsms-border wsms-border-border wsms-bg-popover wsms-shadow-md">
            {/* Search input */}
            <div className="wsms-p-2 wsms-border-b wsms-border-border">
              <div className="wsms-relative">
                <Search className="wsms-absolute wsms-left-2 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder={searchPlaceholder}
                  className="wsms-pl-8 wsms-h-8"
                />
              </div>
            </div>

            {/* Options list with native scroll */}
            <div className="wsms-max-h-[200px] wsms-overflow-y-auto wsms-p-1">
              {filteredOptions.length === 0 ? (
                <div className="wsms-py-4 wsms-text-center wsms-text-sm wsms-text-muted-foreground">
                  No results found
                </div>
              ) : (
                filteredOptions.map((option) => {
                  const isSelected = selectedValues.includes(option.value)
                  return (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => handleSelect(option.value)}
                      className={cn(
                        'wsms-flex wsms-w-full wsms-items-center wsms-gap-2 wsms-rounded wsms-px-2 wsms-py-1.5 wsms-text-left wsms-text-[13px] wsms-outline-none',
                        'hover:wsms-bg-accent focus:wsms-bg-accent',
                        isSelected && 'wsms-bg-accent/50'
                      )}
                    >
                      <div
                        className={cn(
                          'wsms-flex wsms-h-4 wsms-w-4 wsms-items-center wsms-justify-center wsms-rounded wsms-border',
                          isSelected
                            ? 'wsms-border-primary wsms-bg-primary wsms-text-primary-foreground'
                            : 'wsms-border-border'
                        )}
                      >
                        {isSelected && <Check className="wsms-h-3 wsms-w-3" />}
                      </div>
                      <span className="wsms-flex-1 wsms-truncate">{option.label}</span>
                    </button>
                  )
                })
              )}
            </div>

            {/* Footer with selection count */}
            {normalizedOptions.length > 5 && (
              <div className="wsms-border-t wsms-border-border wsms-px-2 wsms-py-1.5 wsms-text-[11px] wsms-text-muted-foreground">
                {selectedValues.length} of {normalizedOptions.length} selected
              </div>
            )}
          </div>
        )}
      </div>
    )
  }
)

MultiSelect.displayName = 'MultiSelect'

export { MultiSelect }
