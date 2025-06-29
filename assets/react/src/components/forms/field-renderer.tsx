import React from 'react'
import { TextField } from './fields/text-field'
import { TextareaField } from './fields/textarea-field'
import { NumberField } from './fields/number-field'
import { CheckboxField } from './fields/checkbox-field'
import { SelectField } from './fields/select-field'
import { MultiSelectField } from './fields/multiselect-field'
import { HeaderField, NoticeField, HtmlField } from './fields/display-fields'
import { RepeaterField } from './repeater-field'
import { GatewayFields } from './gateway-fields'
import { FieldRendererProps, SchemaField } from './types'
import { getDynamicOptions } from './utils'
import { useWordPressMediaUploader } from './hooks/use-wordpress-media-uploader'
import { ColorField } from './settings-fields'
import { PhoneField } from './fields/phone-field'

interface FieldRendererFactoryProps extends FieldRendererProps {
  formData: Record<string, any>
}

export function FieldRenderer({ field, value, onChange, error, formData }: FieldRendererFactoryProps) {
  const { type } = field

  // Handle dynamic options for select fields
  const getFieldWithDynamicOptions = (field: SchemaField) => {
    if (field.options_depends_on) {
      return {
        ...field,
        options: getDynamicOptions(field, formData)
      }
    }
    return field
  }

  const fieldWithOptions = getFieldWithDynamicOptions(field)

  const { openMediaUploader } = useWordPressMediaUploader()

  function ImageField({ field, value, onChange }: any) {
    return (
      <div className="space-y-2">
        <label className="block text-sm font-medium mb-1">{field.label}</label>
        {value && (
          <img src={value} alt="Preview" className="mb-2 rounded border max-h-32" />
        )}
        <button
          type="button"
          className="inline-flex items-center px-3 py-1.5 border rounded bg-muted hover:bg-accent text-sm"
          onClick={() => openMediaUploader(onChange)}
        >
          {value ? 'Change Image' : 'Select Image'}
        </button>
        {field.description && <p className="text-xs text-muted-foreground">{field.description}</p>}
      </div>
    )
  }

  switch (type) {
    case 'text':
      return <TextField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'textarea':
      return <TextareaField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'number':
      return <NumberField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'checkbox':
      return <CheckboxField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'select':
    case 'advancedselect':
    case 'countryselect':
      return <SelectField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'multiselect':
    case 'advancedmultiselect':
      return <MultiSelectField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'repeater':
      return (
        <RepeaterField
          label={fieldWithOptions.label}
          description={fieldWithOptions.description}
          value={value || []}
          onChange={onChange}
        >
          {(index, onUpdate, onRemove) => {
            const itemData = (value || [])[index] || {}
            return (
              <div className="space-y-4">
                {(fieldWithOptions.fieldGroups || []).map((group: any) => (
                  <div key={group.key} className="space-y-4">
                    {group.label && (
                      <h4 className="text-sm font-medium text-muted-foreground">{group.label}</h4>
                    )}
                    {group.description && (
                      <p className="text-xs text-muted-foreground">{group.description}</p>
                    )}
                    <div className={`grid gap-4 ${
                      group.layout === '2-column' ? 'grid-cols-1 md:grid-cols-2' : 
                      group.layout === '3-column' ? 'grid-cols-1 md:grid-cols-3' : 
                      'grid-cols-1'
                    }`}>
                      {(group.fields || []).map((groupField: any) => {
                        const fieldKey = `${fieldWithOptions.key}[${index}][${groupField.key}]`
                        const fieldValue = itemData[groupField.key] || ''
                        return (
                          <div key={groupField.key} className="space-y-2">
                            <FieldRenderer
                              field={groupField}
                              value={fieldValue}
                              onChange={(val: any) => {
                                onUpdate({ [groupField.key]: val })
                              }}
                              error={undefined}
                              formData={formData || {}}
                            />
                          </div>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>
            )
          }}
        </RepeaterField>
      )

    case 'color':
      return <ColorField label={fieldWithOptions.label} value={value} onChange={onChange} description={fieldWithOptions.description} />

    case 'tel':
      return <PhoneField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'gateway':
      return <GatewayFields field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'header':
      return <HeaderField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'notice':
      return <NoticeField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'html':
      return <HtmlField field={fieldWithOptions} value={value} onChange={onChange} error={error} />

    case 'image':
      return <ImageField field={fieldWithOptions} value={value} onChange={onChange} />

    default:
      console.warn(`Unknown field type: ${type}`)
      return <TextField field={fieldWithOptions} value={value} onChange={onChange} error={error} />
  }
} 