import type { CheckboxProps } from '@radix-ui/react-checkbox'
import { Controller, useFormContext } from 'react-hook-form'

import { Checkbox } from '@/components/ui/checkbox'

import type { ControlledFieldProps } from './field-wrapper'
import { FieldWrapper } from './field-wrapper'

export type ControlledCheckboxProps = {
  name: string
} & CheckboxProps &
  ControlledFieldProps

export const ControlledCheckbox = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  isLoading,
  ...props
}: ControlledCheckboxProps) => {
  const { control } = useFormContext()

  return (
    <Controller
      name={name}
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
            direction="row"
          >
            <Checkbox id={name} checked={field.value} onCheckedChange={field.onChange} {...props} />
          </FieldWrapper>
        )
      }}
    />
  )
}
