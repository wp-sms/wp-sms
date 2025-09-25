import { Settings } from 'lucide-react'

import { Button } from '@/components/ui/button'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import { fieldHelpers } from './field-helpers'
import { FieldWrapper } from './field-wrapper'
import { CheckboxField } from './fields/checkbox-field'
import { ColorField } from './fields/color-field'
import { Header, HtmlRenderer, Notice } from './fields/display-fields'
import { ImageField } from './fields/image-field'
import { MultiselectField } from './fields/multiselect-field'
import { NumberField } from './fields/number-field'
import { RepeaterField } from './fields/repeater-field'
import { SelectField } from './fields/select-field'
import { TelField } from './fields/tel-field'
import { TextField } from './fields/text-field'
import { TextareaField } from './fields/textarea-field'

// Simplified FieldApi type for our use case
export type SimpleFieldApi = {
  name: string
  state: {
    value: FieldValue
    meta: {
      errors: string[]
    }
  }
  handleBlur: () => void
  handleChange: (value: FieldValue) => void
}

type FieldRendererProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  onOpenSubFields?: (field: SchemaField) => void
  onFieldValueChange?: (name: string, value: FieldValue) => void
  formValues?: Record<string, unknown>
  defaultValues?: Record<string, unknown>
  groupName?: SettingGroupName
}

export const FieldRenderer = ({
  field,
  fieldApi,
  onOpenSubFields,
  onFieldValueChange,
  formValues,
  defaultValues,
  groupName,
}: FieldRendererProps) => {
  const fieldValue = fieldApi.state.value
  const fieldState = fieldApi.state.meta

  const renderFieldContent = () => {
    switch (field.type) {
      case 'text':
        return <TextField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'textarea':
        return <TextareaField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'number':
        return <NumberField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'select':
      case 'advancedselect':
      case 'countryselect':
        return <SelectField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'multiselect':
        return <MultiselectField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'checkbox':
        return <CheckboxField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'html':
        return <HtmlRenderer field={field} />

      case 'header':
        return <Header field={field} />

      case 'color':
        return <ColorField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'notice':
        return <Notice field={field} />

      case 'repeater':
        return (
          <RepeaterField
            field={field}
            fieldApi={fieldApi}
            fieldValue={fieldValue}
            onFieldValueChange={onFieldValueChange}
            defaultValues={defaultValues}
            formValues={formValues}
            groupName={groupName}
          />
        )

      case 'tel':
        return <TelField fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'image':
        return <ImageField fieldApi={fieldApi} fieldValue={fieldValue} />

      default:
        return <div>Unsupported field type: {field.type}</div>
    }
  }

  const subFields = fieldHelpers.getFieldSubFields(field)
  const hasSubFields = subFields.length > 0

  return (
    <div className="flex items-center gap-2">
      <div className="flex-1">
        {field.type === 'notice' ? (
          renderFieldContent()
        ) : (
          <FieldWrapper schema={field} errors={fieldState.errors}>
            {renderFieldContent()}
          </FieldWrapper>
        )}
      </div>
      {hasSubFields && onOpenSubFields && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => onOpenSubFields(field)}
          className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
        >
          <Settings className="h-4 w-4" />
        </Button>
      )}
    </div>
  )
}
