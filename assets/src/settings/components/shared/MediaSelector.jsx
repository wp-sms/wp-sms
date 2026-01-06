import React, { useCallback } from 'react'
import { Image, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn, __ } from '@/lib/utils'

/**
 * MediaSelector - WordPress Media Library picker
 * Uses wp.media to open the native WordPress media selector
 */
export function MediaSelector({
  value,
  onChange,
  allowedTypes = ['image'],
  buttonText,
  className,
  disabled = false,
}) {
  const handleSelect = useCallback(() => {
    if (disabled || typeof wp === 'undefined' || !wp.media) {
      console.warn('WordPress media library not available')
      return
    }

    const mediaUploader = wp.media({
      title: __('Select Media'),
      library: {
        type: allowedTypes,
      },
      button: {
        text: __('Use this media'),
      },
      multiple: false,
    })

    mediaUploader.on('select', () => {
      const attachment = mediaUploader.state().get('selection').first().toJSON()
      onChange?.(attachment.url)
    })

    mediaUploader.open()
  }, [disabled, allowedTypes, onChange])

  const handleRemove = useCallback((e) => {
    e.stopPropagation()
    onChange?.('')
  }, [onChange])

  // Check if value is an image
  const isImage = value && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(value)

  return (
    <div className={cn('wsms-space-y-2', className)}>
      {value ? (
        <div className="wsms-relative wsms-inline-block">
          {isImage ? (
            <div className="wsms-relative wsms-rounded-lg wsms-overflow-hidden wsms-border wsms-border-border wsms-bg-muted/30">
              <img
                src={value}
                alt={__('Selected media')}
                className="wsms-max-w-[200px] wsms-max-h-[150px] wsms-object-contain"
              />
              <button
                type="button"
                onClick={handleRemove}
                disabled={disabled}
                className="wsms-absolute wsms-top-1 wsms-right-1 wsms-p-1 wsms-rounded-full wsms-bg-destructive wsms-text-destructive-foreground hover:wsms-bg-destructive/90 wsms-transition-colors"
                aria-label={__('Remove media')}
              >
                <X className="wsms-h-3 wsms-w-3" />
              </button>
            </div>
          ) : (
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-2 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-muted/30">
              <Image className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-[12px] wsms-text-foreground wsms-truncate wsms-max-w-[180px]">
                {value.split('/').pop()}
              </span>
              <button
                type="button"
                onClick={handleRemove}
                disabled={disabled}
                className="wsms-p-1 wsms-rounded-full hover:wsms-bg-destructive/10 wsms-text-destructive wsms-transition-colors"
                aria-label={__('Remove media')}
              >
                <X className="wsms-h-3 wsms-w-3" />
              </button>
            </div>
          )}
        </div>
      ) : (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleSelect}
          disabled={disabled}
          className="wsms-gap-2"
        >
          <Image className="wsms-h-4 wsms-w-4" />
          {buttonText || __('Select Media')}
        </Button>
      )}
    </div>
  )
}
