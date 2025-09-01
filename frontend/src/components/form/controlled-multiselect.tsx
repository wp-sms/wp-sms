import { Controller, useFormContext } from 'react-hook-form'

import { MultiSelect } from '@/components/ui/multiselect'
import { toOptions } from '@/lib/to-options'
import type { FieldOption } from '@/types/settings/group-schema'

import type { ControlledFieldProps } from './field-wrapper'
import { FieldWrapper } from './field-wrapper'

export type ControlledMultiselectProps = {
  options: FieldOption
  value: string[]
  name: string
} & ControlledFieldProps

export const ControlledMultiselect = ({ name, label, description, tooltip, tag, isLocked, options, isLoading }) => {
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
            <MultiSelect
              aria-invalid={fieldState?.invalid || !!fieldState?.error}
              value={field?.value ?? []}
              options={toOptions(options) ?? []}
              defaultValue={field?.value ?? []}
              onValueChange={field?.onChange}
            />
          </FieldWrapper>
        )
      }}
    />
  )
}
