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
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type SelectFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const SelectField = ({ field, fieldApi, fieldValue, fieldState }: SelectFieldProps) => {
  console.log('from select', { fieldApi })

  return (
    <Select
      defaultValue={String(field.default)}
      value={String(fieldValue)}
      onValueChange={(value) => fieldApi.handleChange(value)}
      disabled={field.readonly}
    >
      <SelectTrigger
        aria-invalid={!!fieldState.errors.length}
        aria-disabled={field.readonly}
        aria-readonly={field.readonly}
        className="w-full"
        id={field.key}
      >
        <SelectValue className="w-full" placeholder={field.placeholder} />
      </SelectTrigger>
      <SelectContent>
        {toOptions(field.options)?.map((item, index) => {
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
  )
}
