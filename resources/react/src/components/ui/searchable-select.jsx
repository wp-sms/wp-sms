import * as React from 'react'
import { Check, ChevronDown, Search } from 'lucide-react'
import { cn, __ } from '@/lib/utils'
import { Button } from './button'
import { Input } from './input'

/**
 * SearchableSelect component for selecting a single item from a searchable list
 *
 * @param {Object} props
 * @param {Array} props.options - Array of {value, label} objects or array of objects with code/name
 * @param {string} props.value - Selected value
 * @param {Function} props.onValueChange - Callback when selection changes
 * @param {string} props.placeholder - Placeholder text when nothing selected
 * @param {string} props.searchPlaceholder - Placeholder for search input
 * @param {string} props.className - Additional CSS classes
 * @param {boolean} props.disabled - Whether the component is disabled
 */
const SearchableSelect = React.forwardRef(
  (
    {
      options = [],
      value,
      onValueChange,
      placeholder = 'Select...',
      searchPlaceholder = 'Search...',
      className,
      disabled = false,
      triggerClassName,
      optionClassName,
      'aria-label': ariaLabel,
    },
    ref
  ) => {
    const [open, setOpen] = React.useState(false)
    const [search, setSearch] = React.useState('')
    const containerRef = React.useRef(null)
    const searchInputRef = React.useRef(null)

    // Focus search input when dropdown opens
    React.useEffect(() => {
      if (open && searchInputRef.current) {
        // Small delay to ensure dropdown is rendered
        setTimeout(() => searchInputRef.current?.focus(), 10)
      }
      if (!open) {
        setSearch('')
      }
    }, [open])

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

    // Handle keyboard navigation
    React.useEffect(() => {
      if (!open) return

      const handleKeyDown = (event) => {
        if (event.key === 'Escape') {
          setOpen(false)
        }
      }

      document.addEventListener('keydown', handleKeyDown)
      return () => document.removeEventListener('keydown', handleKeyDown)
    }, [open])

    // Normalize options to array format
    const normalizedOptions = React.useMemo(() => {
      if (Array.isArray(options)) {
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
      return Object.entries(options).map(([key, labelOrObj]) => ({
        value: key,
        label:
          typeof labelOrObj === 'string'
            ? labelOrObj
            : labelOrObj && typeof labelOrObj === 'object' && labelOrObj.name
              ? labelOrObj.name
              : key,
      }))
    }, [options])

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

    // Get label for selected value
    const selectedLabel = React.useMemo(() => {
      if (!value) return null
      const option = normalizedOptions.find((o) => o.value === value)
      return option ? option.label : value
    }, [value, normalizedOptions])

    const handleSelect = (optionValue) => {
      onValueChange?.(optionValue)
      setOpen(false)
    }

    return (
      <div ref={containerRef} className={cn('wsms-relative wsms-w-full', className)}>
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
            'wsms-w-full wsms-justify-between wsms-h-9 wsms-px-3 wsms-font-normal',
            !value && 'wsms-text-muted-foreground',
            triggerClassName
          )}
        >
          <span className="wsms-truncate wsms-text-left wsms-flex-1">
            {selectedLabel || placeholder}
          </span>
          <ChevronDown
            className={cn(
              'wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-transition-transform wsms-shrink-0 wsms-ml-2',
              open && 'wsms-rotate-180'
            )}
          />
        </Button>

        {open && (
          <div className="wsms-absolute wsms-z-[9999] wsms-mt-1 wsms-w-full wsms-rounded wsms-border wsms-border-border wsms-bg-popover wsms-shadow-md">
            {/* Search input */}
            <div className="wsms-p-2 wsms-border-b wsms-border-border">
              <div className="wsms-relative">
                <Search className="wsms-absolute wsms-left-2 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  ref={searchInputRef}
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder={searchPlaceholder}
                  className="wsms-pl-8 wsms-h-8"
                />
              </div>
            </div>

            {/* Options list */}
            <div className="wsms-max-h-[200px] wsms-overflow-y-auto wsms-p-1">
              {filteredOptions.length === 0 ? (
                <div className="wsms-py-4 wsms-text-center wsms-text-sm wsms-text-muted-foreground">
                  {__('No results found')}
                </div>
              ) : (
                filteredOptions.map((option) => {
                  const isSelected = value === option.value
                  return (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => handleSelect(option.value)}
                      className={cn(
                        'wsms-flex wsms-w-full wsms-items-center wsms-gap-2 wsms-rounded wsms-px-2 wsms-py-1.5 wsms-text-left wsms-text-[13px] wsms-outline-none',
                        'hover:wsms-bg-accent focus:wsms-bg-accent',
                        isSelected && 'wsms-bg-accent/50',
                        optionClassName
                      )}
                    >
                      <span
                        className={cn(
                          'wsms-flex wsms-h-4 wsms-w-4 wsms-items-center wsms-justify-center wsms-shrink-0',
                          isSelected ? 'wsms-text-primary' : 'wsms-text-transparent'
                        )}
                      >
                        <Check className="wsms-h-4 wsms-w-4" />
                      </span>
                      <span className="wsms-flex-1 wsms-truncate">{option.label}</span>
                    </button>
                  )
                })
              )}
            </div>
          </div>
        )}
      </div>
    )
  }
)

SearchableSelect.displayName = 'SearchableSelect'

export { SearchableSelect }
