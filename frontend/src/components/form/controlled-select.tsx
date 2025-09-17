import type {
  SelectContentProps,
  SelectGroupProps,
  SelectItemProps,
  SelectProps,
  SelectTriggerProps,
  SelectValueProps,
} from '@radix-ui/react-select'
import { Controller, useFormContext } from 'react-hook-form'

import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { toOptions } from '@/lib/to-options'
import type { FieldOption } from '@/types/settings/group-schema'

import type { ControlledFieldProps } from './field-wrapper'
import { FieldWrapper } from './field-wrapper'

export type ControlledSelectProps = {
  name: string
  options: FieldOption
  readOnly?: boolean
  disabled?: boolean
  placeholder?: string
  selectProps?: SelectProps
  triggerProps?: SelectTriggerProps
  SelectValueProps?: SelectValueProps
  SelectContentProps?: SelectContentProps
  SelectGroupProps?: SelectGroupProps
  SelectItemProps?: SelectItemProps
} & ControlledFieldProps

export const ControlledSelect = ({
  name,
  options,
  placeholder,
  readOnly,
  disabled,
  SelectContentProps,
  SelectGroupProps,
  SelectItemProps,
  SelectValueProps,
  selectProps,
  triggerProps,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  isLoading,
}: ControlledSelectProps) => {
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
            <Select
              disabled={disabled || readOnly}
              onValueChange={field.onChange}
              defaultValue={field.value}
              value={field.value}
              {...selectProps}
            >
              <SelectTrigger
                aria-invalid={!!fieldState?.invalid || !!fieldState?.error}
                aria-disabled={disabled || readOnly}
                aria-readonly={readOnly}
                className="w-full"
                {...triggerProps}
              >
                <SelectValue id={name} className="w-full" placeholder={placeholder} {...SelectValueProps} />
              </SelectTrigger>

              <SelectContent {...SelectContentProps}>
                {toOptions(options)?.map((item) => {
                  if (item?.children) {
                    return (
                      <SelectGroup key={`select-group-${item.value}`} {...SelectGroupProps}>
                        <SelectLabel>{item.label}</SelectLabel>

                        {item.children?.map((child) => {
                          return (
                            <SelectItem
                              key={`group-select-item-${child.value}`}
                              value={String(child.value)}
                              {...SelectItemProps}
                            >
                              {child.label}
                            </SelectItem>
                          )
                        })}
                      </SelectGroup>
                    )
                  }

                  return (
                    <SelectItem key={`select-item-${item.value}`} value={item?.value} {...SelectItemProps}>
                      {item.label}
                    </SelectItem>
                  )
                })}
              </SelectContent>
            </Select>
          </FieldWrapper>
        )
      }}
    />
  )
}
