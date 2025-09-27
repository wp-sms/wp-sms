import { useStore } from '@tanstack/react-form'

import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useFieldContext } from '@/context/form-context'
import { toOptions } from '@/lib/to-options'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type SelectFieldProps = {
  schema: SchemaField
}

export const SelectField = ({ schema }: SelectFieldProps) => {
  const field = useFieldContext<string>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  console.log('value from select field: ', field.name, field.state.value)

  return (
    <FieldWrapper schema={schema} errors={errors}>
      <Select
        onValueChange={(value) => field.handleChange(value)}
        defaultValue={String(field.state.value || schema.default)}
        disabled={schema.readonly}
      >
        <SelectTrigger
          aria-invalid={!!errors.length}
          aria-disabled={schema.readonly}
          aria-readonly={schema.readonly}
          className="w-full"
        >
          <SelectValue placeholder={schema.placeholder} />
        </SelectTrigger>
        <SelectContent>
          {toOptions(schema.options)?.map((item, index) => {
            if (item?.children) {
              return (
                <SelectGroup key={`select-group-${item.value}-${index}`}>
                  <SelectLabel>{item.label}</SelectLabel>

                  {item.children?.map((child, j) => {
                    return (
                      <SelectItem key={`group-select-item-${child.value}${j}`} value={String(child.value)}>
                        {child.label}
                      </SelectItem>
                    )
                  })}
                </SelectGroup>
              )
            }

            return (
              <SelectItem key={`select-item-${item.value}-${index}`} value={String(item.value)}>
                {item.label}
              </SelectItem>
            )
          })}
        </SelectContent>
      </Select>
    </FieldWrapper>
  )
}
