import React, { useCallback, useState } from 'react'
import { Image, X, ImageOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn, __ } from '@/lib/utils'

/**
 * Check if URL looks like an image (handles query params)
 */
function isImageUrl(url) {
  if (!url) return false
  // Remove query params and hash for extension check
  const cleanUrl = url.split('?')[0].split('#')[0]
  return /\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)$/i.test(cleanUrl)
}

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
  compact = false,
}) {
  const [imageError, setImageError] = useState(false)

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
      setImageError(false)
      onChange?.(attachment.url)
    })

    mediaUploader.open()
  }, [disabled, allowedTypes, onChange])

  const handleRemove = useCallback((e) => {
    e.stopPropagation()
    setImageError(false)
    onChange?.('')
  }, [onChange])

  const handleImageError = useCallback(() => {
    setImageError(true)
  }, [])

  const isImage = isImageUrl(value)

  // Compact mode for use in repeaters
  if (compact && value) {
    return (
      <div className={cn('wsms-flex wsms-items-center wsms-gap-2', className)}>
        {isImage && !imageError ? (
          <img
            src={value}
            alt=""
            onError={handleImageError}
            className="wsms-h-9 wsms-w-9 wsms-rounded wsms-object-cover wsms-border wsms-border-border"
          />
        ) : (
          <div className="wsms-h-9 wsms-w-9 wsms-rounded wsms-border wsms-border-border wsms-bg-muted wsms-flex wsms-items-center wsms-justify-center">
            <ImageOff className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
          </div>
        )}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleSelect}
          disabled={disabled}
          className="wsms-h-9 wsms-text-xs"
        >
          {__('Change')}
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={handleRemove}
          disabled={disabled}
          className="wsms-h-9 wsms-w-9 wsms-p-0 wsms-text-muted-foreground hover:wsms-text-destructive"
        >
          <X className="wsms-h-4 wsms-w-4" />
        </Button>
      </div>
    )
  }

  return (
    <div className={cn('wsms-space-y-2', className)}>
      {value ? (
        <div className="wsms-relative wsms-inline-block">
          {isImage && !imageError ? (
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <div className="wsms-relative wsms-rounded wsms-overflow-hidden wsms-border wsms-border-border wsms-bg-muted/30">
                <img
                  src={value}
                  alt={__('Selected media')}
                  onError={handleImageError}
                  className="wsms-w-9 wsms-h-9 wsms-object-cover"
                />
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleSelect}
                disabled={disabled}
                className="wsms-h-9 wsms-text-xs"
              >
                {__('Change')}
              </Button>
              <button
                type="button"
                onClick={handleRemove}
                disabled={disabled}
                className="wsms-p-1.5 wsms-rounded hover:wsms-bg-destructive/10 wsms-text-muted-foreground hover:wsms-text-destructive wsms-transition-colors"
                aria-label={__('Remove media')}
              >
                <X className="wsms-h-4 wsms-w-4" />
              </button>
            </div>
          ) : (
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-2 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-muted/30">
              {imageError ? (
                <ImageOff className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              ) : (
                <Image className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              )}
              <span className="wsms-text-[12px] wsms-text-foreground wsms-truncate wsms-max-w-[180px]">
                {value.split('/').pop().split('?')[0]}
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
