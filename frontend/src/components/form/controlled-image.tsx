import { CloudUpload } from 'lucide-react'
import { Controller, useFormContext } from 'react-hook-form'

import { Button } from '@/components/ui/button'
import { useWordPressMediaUploader } from '@/hooks/use-wordpress-media-uploader'

import type { ControlledFieldProps } from './field-wrapper'
import { FieldWrapper } from './field-wrapper'

export type ControlledImageProps = {
  name: string
} & ControlledFieldProps

export const ControlledImage = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  isLoading,
}: ControlledImageProps) => {
  const { openMediaUploader } = useWordPressMediaUploader()

  const { control } = useFormContext()

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => {
        return (
          <FieldWrapper
            label={label}
            description={description}
            isLoading={isLoading}
            error={fieldState?.error?.message}
            isLocked={isLocked}
            tag={tag}
            tooltip={tooltip}
          >
            <Button variant="outline" type="button" onClick={() => openMediaUploader(field.onChange)}>
              <CloudUpload />

              {field.value ? 'Change Image' : 'Select Image'}
            </Button>
          </FieldWrapper>
        )
      }}
    />
  )
}
