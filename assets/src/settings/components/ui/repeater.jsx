import * as React from 'react'
import { Plus, Trash2, ChevronUp, ChevronDown } from 'lucide-react'
import { cn, __ } from '@/lib/utils'
import { Button } from './button'
import { Input } from './input'
import { Label } from './label'
import { MediaSelector } from '@/components/shared/MediaSelector'

/**
 * Repeater component for managing arrays of items with configurable fields
 *
 * @param {Object} props
 * @param {Array} props.value - Array of item objects
 * @param {Function} props.onValueChange - Callback when items change
 * @param {Array} props.fields - Array of field definitions [{name, label, type, placeholder}]
 * @param {string} props.addLabel - Label for add button
 * @param {number} props.maxItems - Maximum number of items allowed
 * @param {string} props.className - Additional CSS classes
 * @param {boolean} props.disabled - Whether the component is disabled
 */
const Repeater = React.forwardRef(
  (
    {
      value = [],
      onValueChange,
      fields = [],
      addLabel = 'Add Item',
      maxItems = 10,
      className,
      disabled = false,
      emptyMessage = 'No items added yet',
    },
    ref
  ) => {
    // Ensure value is always an array
    const items = React.useMemo(() => {
      if (!value) return []
      if (Array.isArray(value)) return value
      return []
    }, [value])

    const handleAddItem = () => {
      if (items.length >= maxItems) return

      // Create empty item with all field keys
      const newItem = {}
      fields.forEach((field) => {
        newItem[field.name] = field.defaultValue || ''
      })

      onValueChange?.([...items, newItem])
    }

    const handleRemoveItem = (index) => {
      const newItems = items.filter((_, i) => i !== index)
      onValueChange?.(newItems)
    }

    const handleItemChange = (index, fieldName, fieldValue) => {
      const newItems = [...items]
      newItems[index] = {
        ...newItems[index],
        [fieldName]: fieldValue,
      }
      onValueChange?.(newItems)
    }

    const handleMoveUp = (index) => {
      if (index === 0) return
      const newItems = [...items]
      ;[newItems[index - 1], newItems[index]] = [newItems[index], newItems[index - 1]]
      onValueChange?.(newItems)
    }

    const handleMoveDown = (index) => {
      if (index === items.length - 1) return
      const newItems = [...items]
      ;[newItems[index], newItems[index + 1]] = [newItems[index + 1], newItems[index]]
      onValueChange?.(newItems)
    }

    const canAddMore = items.length < maxItems && !disabled

    return (
      <div ref={ref} className={cn('wsms-space-y-3', className)}>
        {/* Items list */}
        {items.length === 0 ? (
          <div className="wsms-rounded-md wsms-border wsms-border-dashed wsms-border-border wsms-p-4 wsms-text-center wsms-text-sm wsms-text-muted-foreground">
            {emptyMessage}
          </div>
        ) : (
          <div className="wsms-space-y-2">
            {items.map((item, index) => (
              <div
                key={index}
                className="wsms-flex wsms-items-start wsms-gap-2 wsms-rounded-md wsms-border wsms-border-border wsms-bg-card wsms-p-3 wsms-shadow-sm"
              >
                {/* Reorder buttons */}
                <div className="wsms-flex wsms-flex-col wsms-gap-0.5">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => handleMoveUp(index)}
                    disabled={disabled || index === 0}
                    className="wsms-h-6 wsms-w-6 wsms-text-muted-foreground hover:wsms-text-foreground disabled:wsms-opacity-30"
                    aria-label={`Move item ${index + 1} up`}
                  >
                    <ChevronUp className="wsms-h-4 wsms-w-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => handleMoveDown(index)}
                    disabled={disabled || index === items.length - 1}
                    className="wsms-h-6 wsms-w-6 wsms-text-muted-foreground hover:wsms-text-foreground disabled:wsms-opacity-30"
                    aria-label={`Move item ${index + 1} down`}
                  >
                    <ChevronDown className="wsms-h-4 wsms-w-4" />
                  </Button>
                </div>

                {/* Fields */}
                <div className="wsms-flex-1 wsms-min-w-0">
                  {fields.length === 1 ? (
                    // Single field - no label needed
                    <RepeaterField
                      field={fields[0]}
                      value={item[fields[0].name] || ''}
                      onChange={(val) => handleItemChange(index, fields[0].name, val)}
                      disabled={disabled}
                    />
                  ) : fields.length === 2 ? (
                    // Two fields - side by side
                    <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2">
                      {fields.map((field) => (
                        <div key={field.name} className="wsms-space-y-1">
                          <Label className="wsms-text-[11px] wsms-text-muted-foreground wsms-font-medium">
                            {field.label}
                          </Label>
                          <RepeaterField
                            field={field}
                            value={item[field.name] || ''}
                            onChange={(val) => handleItemChange(index, field.name, val)}
                            disabled={disabled}
                          />
                        </div>
                      ))}
                    </div>
                  ) : (
                    // Multiple fields - 2-column grid with colSpan support
                    <div className="wsms-grid wsms-grid-cols-2 wsms-gap-x-3 wsms-gap-y-2">
                      {fields.map((field) => (
                        <div
                          key={field.name}
                          className={cn(
                            'wsms-space-y-1',
                            field.colSpan === 2 && 'wsms-col-span-2'
                          )}
                        >
                          <Label className="wsms-text-[11px] wsms-text-muted-foreground wsms-font-medium">
                            {field.label}
                          </Label>
                          <RepeaterField
                            field={field}
                            value={item[field.name] || ''}
                            onChange={(val) => handleItemChange(index, field.name, val)}
                            disabled={disabled}
                          />
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Delete button */}
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  onClick={() => handleRemoveItem(index)}
                  disabled={disabled}
                  className="wsms-h-8 wsms-w-8 wsms-text-muted-foreground hover:wsms-text-destructive hover:wsms-bg-destructive/10"
                  aria-label={`Remove item ${index + 1}`}
                >
                  <Trash2 className="wsms-h-4 wsms-w-4" />
                </Button>
              </div>
            ))}
          </div>
        )}

        {/* Add button */}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleAddItem}
          disabled={!canAddMore}
          className="wsms-w-full wsms-border-dashed"
        >
          <Plus className="wsms-h-4 wsms-w-4 wsms-mr-1.5" />
          {addLabel}
          {maxItems && items.length > 0 && (
            <span className="wsms-ml-1.5 wsms-text-muted-foreground">
              ({items.length}/{maxItems})
            </span>
          )}
        </Button>
      </div>
    )
  }
)

Repeater.displayName = 'Repeater'

/**
 * Individual field renderer for repeater items
 */
function RepeaterField({ field, value, onChange, disabled }) {
  const { type = 'text', placeholder, options, buttonText } = field

  switch (type) {
    case 'select':
      return (
        <select
          value={value}
          onChange={(e) => onChange(e.target.value)}
          disabled={disabled}
          className="wsms-h-9 wsms-w-full wsms-rounded-md wsms-border wsms-border-input wsms-bg-card wsms-px-3 wsms-py-1 wsms-text-[13px] wsms-shadow-sm focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20 focus:wsms-border-primary disabled:wsms-opacity-50"
          style={{ width: '100%', minWidth: 0 }}
        >
          <option value="">{placeholder || __('Select...')}</option>
          {options?.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      )

    case 'media':
      return (
        <MediaSelector
          value={value}
          onChange={onChange}
          disabled={disabled}
          buttonText={buttonText || __('Select Image')}
          allowedTypes={['image']}
          compact={true}
        />
      )

    case 'url':
      return (
        <Input
          type="url"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder || 'https://'}
          disabled={disabled}
        />
      )

    case 'tel':
      return (
        <Input
          type="tel"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder || '+1234567890'}
          disabled={disabled}
        />
      )

    case 'text':
    default:
      return (
        <Input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          disabled={disabled}
        />
      )
  }
}

export { Repeater }
