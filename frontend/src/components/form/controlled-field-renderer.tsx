import { AlertCircle } from 'lucide-react'
import { useEffect } from 'react'
import { useFormContext, useWatch } from 'react-hook-form'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { HeaderField } from '@/components/ui/header-field'
import { NoticeField } from '@/components/ui/notice-field'
import { SimpleHtmlRenderer } from '@/components/ui/simple-html-rendere'
import { useDebounce } from '@/hooks/use-debounce'
import { useSaveSettingsValues } from '@/services/settings/use-save-settings-values'
import type { SchemaFieldType } from '@/types/settings/group-schema'
import type { SchemaField } from '@/types/settings/group-schema'

import { ControlledCheckbox } from './controlled-checkbox'
import { ControlledColor } from './controlled-color'
import { ControlledImage } from './controlled-image'
import { ControlledInput } from './controlled-input'
import { ControlledMultiselect } from './controlled-multiselect'
import { ControlledNumberInput } from './controlled-number-input'
import { ControlledPhone } from './controlled-phone'
import { ControlledRepeater } from './controlled-repeater'
import { ControlledSelect } from './controlled-select'
import { ControlledTextarea } from './controlled-textarea'

export type ControlledFieldRendererProps = {
  schema: SchemaField
  isLoading?: boolean
}

export const ControlledFieldRenderer: React.FC<ControlledFieldRendererProps> = ({ schema, isLoading = false }) => {
  const FieldComponentMap: Record<SchemaFieldType, React.FC<any>> = {
    text: ControlledInput,
    textarea: ControlledTextarea,
    number: ControlledNumberInput,

    select: ControlledSelect,
    advancedselect: ControlledSelect,
    countryselect: ControlledSelect,

    multiselect: ControlledMultiselect,

    checkbox: ControlledCheckbox,
    html: SimpleHtmlRenderer,
    repeater: ControlledRepeater,
    tel: ControlledPhone,
    color: ControlledColor,

    header: HeaderField,
    notice: NoticeField,

    image: ControlledImage,
  }

  const fieldValue = useWatch({ name: schema?.key, exact: true })

  const { formState } = useFormContext()

  const debouncedFieldValue = useDebounce(fieldValue, 500)

  const saveSettings = useSaveSettingsValues()

  useEffect(() => {
    const defaultValue = formState?.defaultValues?.[schema?.key]

    if (schema.auto_save_and_refresh && defaultValue !== fieldValue) {
      saveSettings.mutate({ [schema?.key]: fieldValue })
    }
  }, [schema?.key, debouncedFieldValue, formState?.defaultValues])

  const FieldComponent = FieldComponentMap?.[schema?.type]

  if (!FieldComponent) {
    return (
      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>Not supported field type.</AlertDescription>
      </Alert>
    )
  }

  return (
    <FieldComponent
      name={schema?.key}
      label={schema?.label}
      options={schema?.options}
      description={schema?.description}
      disabled={schema?.readonly}
      htmlContent={
        schema?.type === 'html' && !schema?.description && typeof schema?.options === 'string'
          ? schema?.options
          : schema?.description
      }
      isLoading={isLoading}
      isLocked={schema?.readonly}
      tag={schema?.tag}
      isRequired={false}
      fieldGroups={schema?.fieldGroups}
    />
  )
}
