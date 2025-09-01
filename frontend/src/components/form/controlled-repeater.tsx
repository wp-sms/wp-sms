import { cva } from 'class-variance-authority'
import { Grip, Plus, Trash2Icon } from 'lucide-react'
import { useCallback } from 'react'
import { useFieldArray, useWatch } from 'react-hook-form'

import { Button } from '@/components/ui/button'
import { ConfirmAction } from '@/components/ui/confirm-action'
import type { SchemaField, SchemaFieldLayout } from '@/types/settings/group-schema'

import { ControlledFieldRenderer } from './controlled-field-renderer'

export type ControlledRepeaterProps = {
  name: string
  layout?: SchemaFieldLayout
  fieldGroups: SchemaField['fieldGroups']
}

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

export const ControlledRepeater = ({ name, fieldGroups, layout = '2-column' }: ControlledRepeaterProps) => {
  const formValues = useWatch()

  const { fields, append, remove } = useFieldArray({ name: name })

  const handleAddItem = useCallback(() => {
    const newFieldData = Object.fromEntries(Object.entries(fields?.[0] ?? {}).map(([key]) => [key, null]))

    append({ ...newFieldData, id: `item-${Date.now()}` })
  }, [fields, append])

  const handleRemoveItem = useCallback(
    (idx: number) => {
      remove(idx)
    },
    [remove]
  )

  return (
    <div className="flex flex-col gap-y-4">
      {fields?.map((item, idx) => {
        return (
          <div key={item?.id} className="flex flex-col gap-y-6 border border-border rounded-lg p-4">
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

            {fieldGroups?.map((group) => {
              return (
                <section key={`${name}-${item?.id}`} className={layoutVariants({ layout })}>
                  {group?.fields?.map((field) => {
                    const shouldShow = Object.entries(field?.showIf ?? {}).every(([key, expectedValue]) => {
                      return formValues[key] === expectedValue
                    })

                    const shouldHide = Object.entries(field?.hideIf ?? {}).some(([key, expectedValue]) => {
                      return formValues[key] === expectedValue
                    })

                    if (!shouldShow || shouldHide || Boolean(field?.hidden)) {
                      return null
                    }

                    return (
                      <ControlledFieldRenderer
                        isLoading={false}
                        key={`group-${group?.key}-field-${field?.key}`}
                        schema={{ ...field, key: `${name}.${idx}.${field?.key}` }}
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
        <span>{`Add ${fieldGroups?.[0]?.label}`}</span>
      </Button>
    </div>
  )
}
