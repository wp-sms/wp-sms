import { cva } from 'class-variance-authority'
import { Grip, Plus, Trash2Icon } from 'lucide-react'
import { useCallback, useMemo } from 'react'

import { Button } from '@/components/ui/button'
import { ConfirmAction } from '@/components/ui/confirm-action'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'
import { FieldRenderer } from '../field-renderer'

type RepeaterItem = Record<string, unknown> & { id?: string }

const layoutVariants = cva('', {
  variants: {
    layout: {
      '1-column': 'grid grid-cols-1 gap-4',
      '2-column': 'grid grid-cols-2 gap-4',
      '3-column': 'grid grid-cols-3 gap-4',
      '4-column': 'grid grid-cols-4 gap-4',
      '5-column': 'grid grid-cols-5 gap-4',
      '6-column': 'grid grid-cols-6 gap-4',
      '7-column': 'grid grid-cols-7 gap-4',
      '8-column': 'grid grid-cols-8 gap-4',
      '9-column': 'grid grid-cols-9 gap-4',
      '10-column': 'grid grid-cols-10 gap-4',
      '11-column': 'grid grid-cols-11 gap-4',
      '12-column': 'grid grid-cols-12 gap-4',
    },
  },
  defaultVariants: {
    layout: '2-column',
  },
})

type RepeaterFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  onFieldValueChange?: (name: string, value: FieldValue) => void
  formValues?: Record<string, unknown>
  defaultValues?: Record<string, unknown>
  groupName?: SettingGroupName
}

export const RepeaterField = ({
  field,
  fieldApi,
  fieldValue,
  formValues,
  defaultValues,
  groupName,
}: RepeaterFieldProps) => {
  const fieldsArray = useMemo(() => {
    if (Array.isArray(fieldValue)) {
      if (fieldValue.every((item) => typeof item === 'object' && item !== null)) {
        return fieldValue as RepeaterItem[]
      }
    }
    return []
  }, [fieldValue])

  const layout = '2-column'

  const handleAddItem = useCallback(() => {
    const firstItem = fieldsArray?.[0]
    const newFieldData = firstItem ? Object.fromEntries(Object.entries(firstItem).map(([key]) => [key, null])) : {}
    const newItem: RepeaterItem = { ...newFieldData, id: `item-${Date.now()}` }
    const newArray = [...fieldsArray, newItem]
    fieldApi.handleChange(newArray as FieldValue)
  }, [fieldsArray, fieldApi])

  const handleRemoveItem = useCallback(
    (idx: number) => {
      const newArray = fieldsArray.filter((_, index) => index !== idx)
      fieldApi.handleChange(newArray as FieldValue)
    },
    [fieldsArray, fieldApi]
  )

  const handleItemFieldChange = useCallback(
    (itemIndex: number, fieldKey: string, value: FieldValue) => {
      const newArray = [...fieldsArray]
      const currentItem = newArray[itemIndex]
      if (currentItem) {
        newArray[itemIndex] = { ...currentItem, [fieldKey]: value }
        fieldApi.handleChange(newArray as FieldValue)
      }
    },
    [fieldsArray, fieldApi]
  )

  return (
    <div className="flex flex-col gap-y-4">
      {fieldsArray?.map((item, idx) => {
        return (
          <div key={item?.id || `item-${idx}`} className="flex flex-col gap-y-6 border border-border rounded-lg p-4">
            <div className="flex justify-between items-center">
              <div className="flex items-center gap-x-2">
                <Grip size={20} className="text-foreground" />
                <p className="text-base font-medium text-foreground">{`Item ${idx + 1}`}</p>
              </div>

              <div className="flex items-center gap-x-2">
                <ConfirmAction onConfirm={() => handleRemoveItem(idx)}>
                  <Button variant="ghost" size="icon">
                    <Trash2Icon className="w-4 h-4" />
                  </Button>
                </ConfirmAction>
              </div>
            </div>

            {field.fieldGroups?.map((group) => {
              return (
                <section key={`${field.key}-${item?.id || `item-${idx}`}`} className={layoutVariants({ layout })}>
                  {group?.fields?.map((subField) => {
                    const shouldShow = Object.entries(subField?.showIf ?? {}).every(([key, expectedValue]) => {
                      return formValues?.[key] === expectedValue
                    })

                    const shouldHide = Object.entries(subField?.hideIf ?? {}).some(([key, expectedValue]) => {
                      return formValues?.[key] === expectedValue
                    })

                    if (!shouldShow || shouldHide || Boolean(subField?.hidden)) {
                      return null
                    }

                    const subFieldApi: SimpleFieldApi = {
                      name: `${field.key}.${idx}.${subField.key}`,
                      state: {
                        value:
                          item && typeof item === 'object' && subField.key in item
                            ? (item[subField.key] as FieldValue)
                            : undefined,
                        meta: { errors: [] },
                      },
                      handleBlur: () => {},
                      handleChange: (value) => handleItemFieldChange(idx, subField.key, value),
                    }

                    return (
                      <FieldRenderer
                        key={`group-${group?.key}-field-${subField?.key}`}
                        field={{ ...subField, key: `${field.key}.${idx}.${subField?.key}` }}
                        fieldApi={subFieldApi}
                        defaultValues={defaultValues}
                        groupName={groupName}
                      />
                    )
                  })}
                </section>
              )
            })}
          </div>
        )
      })}

      <Button
        onClick={handleAddItem}
        type="button"
        variant="outline"
        size="sm"
        className="flex items-center justify-center gap-x-1 w-full"
      >
        <Plus size={18} />
        <span>{`Add ${field.fieldGroups?.[0]?.label || 'Item'}`}</span>
      </Button>
    </div>
  )
}
