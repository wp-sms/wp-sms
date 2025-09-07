import { Controller, useFormContext } from 'react-hook-form'

import { Input } from '@/components/ui/input'

import type { ControlledFieldProps } from './field-wrapper'
import { FieldWrapper } from './field-wrapper'

export type ControlledNumberInputProps = React.ComponentProps<'input'> & ControlledFieldProps

export const ControlledNumberInput = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  isLoading,
  ...props
}: ControlledNumberInputProps) => {
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
            <Input
              type="number"
              aria-invalid={!!fieldState?.invalid || !!fieldState?.error?.message}
              {...field}
              {...props}
            />
          </FieldWrapper>
        )
      }}
    />
  )
}
