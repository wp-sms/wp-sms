import { Controller, useFormContext } from 'react-hook-form'

import { Textarea } from '@/components/ui/textarea'

import { type ControlledFieldProps, FieldWrapper } from './field-wrapper'

export type ControlledTextareaProps = React.ComponentProps<'textarea'> & ControlledFieldProps

export const ControlledTextarea = ({ label, description, tooltip, tag, isLocked, isLoading, name, ...props }) => {
  const { control } = useFormContext()

  return (
    <Controller
      name={name ?? ''}
      control={control}
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
            <Textarea aria-invalid={fieldState?.invalid || !!fieldState?.error} {...field} {...props} />
          </FieldWrapper>
        )
      }}
    />
  )
}
