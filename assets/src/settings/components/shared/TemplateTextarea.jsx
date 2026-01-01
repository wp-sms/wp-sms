import React, { useRef, useCallback } from 'react'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'

/**
 * TemplateTextarea - A textarea with clickable variable chips for easy insertion
 *
 * @param {string} value - Current textarea value
 * @param {function} onChange - Callback when value changes (receives new value string)
 * @param {string[]} variables - Array of variable strings (e.g., ['%post_title%', '%post_url%'])
 * @param {string} [placeholder] - Placeholder text
 * @param {number} [rows=3] - Number of textarea rows
 * @param {string} [id] - Optional id for the textarea
 * @param {string} [className] - Optional className for the wrapper
 */
export function TemplateTextarea({
  value,
  onChange,
  variables = [],
  placeholder,
  rows = 3,
  id,
  className,
}) {
  const textareaRef = useRef(null)

  const handleInsertVariable = useCallback((variable) => {
    const textarea = textareaRef.current
    if (!textarea) return

    const start = textarea.selectionStart || 0
    const end = textarea.selectionEnd || 0

    const newValue = (value || '').slice(0, start) + variable + (value || '').slice(end)
    onChange(newValue)

    // Restore focus and set cursor position after the inserted variable
    requestAnimationFrame(() => {
      textarea.focus()
      const newCursorPos = start + variable.length
      textarea.setSelectionRange(newCursorPos, newCursorPos)
    })
  }, [value, onChange])

  return (
    <div className={cn('wsms-space-y-2', className)}>
      <Textarea
        ref={textareaRef}
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        rows={rows}
      />
      {variables.length > 0 && (
        <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-1.5">
          <span className="wsms-text-xs wsms-text-muted-foreground wsms-mr-1">Insert:</span>
          {variables.map((variable) => (
            <button
              key={variable}
              type="button"
              onClick={() => handleInsertVariable(variable)}
              className={cn(
                'wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border',
                'wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono',
                'wsms-text-muted-foreground wsms-bg-muted/30',
                'hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary',
                'wsms-transition-colors wsms-cursor-pointer',
                'focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20'
              )}
            >
              {variable}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
