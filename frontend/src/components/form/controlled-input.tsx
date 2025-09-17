import { Controller, useFormContext } from 'react-hook-form'

import { Input } from '@/components/ui/input'

import type { ControlledFieldProps } from './field-wrapper'
import { FieldWrapper } from './field-wrapper'

export type ControlledInputProps = React.ComponentProps<'input'> & ControlledFieldProps

export const ControlledInput = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  isLoading,
  ...props
}: ControlledInputProps) => {
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
            <Input aria-invalid={fieldState?.invalid || !!fieldState?.error} {...field} {...props} />
          </FieldWrapper>
        )
      }}
    />
  )
}
