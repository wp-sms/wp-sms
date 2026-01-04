import React, { useMemo } from 'react'
import PropTypes from 'prop-types'
import { InputField, TextareaField, SelectField, SwitchField, FieldDescription } from './form-field'
import { MultiSelect } from './multi-select'
import { Repeater } from './repeater'
import { Label } from './label'
import { useSetting, useProSetting, useSettings } from '@/context/SettingsContext'

/**
 * Evaluate conditional display rules
 *
 * @param {Array} conditions - Array of condition objects
 * @param {Object} settings - Current settings values
 * @param {Object} proSettings - Current pro settings values
 * @returns {boolean} Whether all conditions are met
 */
function evaluateConditions(conditions, settings, proSettings) {
  if (!conditions || conditions.length === 0) return true

  return conditions.every(condition => {
    // Get value from either settings or proSettings
    const value = settings[condition.field] ?? proSettings[condition.field]

    switch (condition.operator) {
      case '==':
        return value === condition.value
      case '!=':
        return value !== condition.value
      case 'contains':
        return Array.isArray(value) && value.includes(condition.value)
      case 'empty':
        return !value || (Array.isArray(value) && value.length === 0)
      case 'notEmpty':
        return value && (!Array.isArray(value) || value.length > 0)
      default:
        return true
    }
  })
}

/**
 * DynamicField - Renders a form field based on schema definition from add-ons
 *
 * This component dynamically renders the appropriate form field type based on
 * the field schema provided by add-ons via the wpsms_addon_settings_schema filter.
 *
 * @param {Object} props
 * @param {Object} props.field - Field schema definition
 */
export function DynamicField({ field }) {
  const { settings, proSettings } = useSettings()

  // Use appropriate hook based on isPro flag
  const [value, setValue] = field.isPro
    ? useProSetting(field.id, field.default ?? '')
    : useSetting(field.id, field.default ?? '')

  // Evaluate conditions
  const isVisible = useMemo(() => {
    return evaluateConditions(field.conditions, settings, proSettings)
  }, [field.conditions, settings, proSettings])

  if (!isVisible) return null

  const commonProps = {
    label: field.label,
    description: field.description,
    required: field.required,
    disabled: field.disabled,
  }

  switch (field.type) {
    case 'text':
      return (
        <InputField
          {...commonProps}
          type="text"
          value={value || ''}
          onChange={(e) => setValue(e.target.value)}
          placeholder={field.placeholder}
        />
      )

    case 'password':
      return (
        <InputField
          {...commonProps}
          type="password"
          value={value || ''}
          onChange={(e) => setValue(e.target.value)}
          placeholder={field.placeholder}
        />
      )

    case 'number':
      return (
        <InputField
          {...commonProps}
          type="number"
          value={value ?? ''}
          onChange={(e) => setValue(e.target.value)}
          placeholder={field.placeholder}
          min={field.validation?.min}
          max={field.validation?.max}
        />
      )

    case 'textarea':
      return (
        <TextareaField
          {...commonProps}
          value={value || ''}
          onChange={(e) => setValue(e.target.value)}
          placeholder={field.placeholder}
          rows={field.rows || 3}
        />
      )

    case 'select':
      return (
        <SelectField
          {...commonProps}
          value={value || ''}
          onValueChange={setValue}
          placeholder={field.placeholder || 'Select an option...'}
          options={field.options || []}
        />
      )

    case 'multi-select':
      return (
        <div className="wsms-space-y-2">
          {field.label && (
            <Label className="wsms-text-[13px] wsms-font-medium">
              {field.label}
              {field.required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
            </Label>
          )}
          <MultiSelect
            options={field.options || []}
            value={Array.isArray(value) ? value : []}
            onValueChange={setValue}
            placeholder={field.placeholder || 'Select items...'}
            disabled={field.disabled}
          />
          {field.description && (
            <FieldDescription>{field.description}</FieldDescription>
          )}
        </div>
      )

    case 'switch':
      return (
        <SwitchField
          label={field.label}
          description={field.description}
          checked={value === true || value === '1' || value === 1}
          onCheckedChange={(checked) => setValue(checked)}
          disabled={field.disabled}
        />
      )

    case 'checkbox':
      return (
        <SwitchField
          label={field.label}
          description={field.description}
          checked={value === true || value === '1' || value === 1}
          onCheckedChange={(checked) => setValue(checked)}
          disabled={field.disabled}
        />
      )

    case 'repeater':
      return (
        <div className="wsms-space-y-2">
          {field.label && (
            <Label className="wsms-text-[13px] wsms-font-medium">
              {field.label}
              {field.required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
            </Label>
          )}
          <Repeater
            value={Array.isArray(value) ? value : []}
            onChange={setValue}
            fields={field.fields || []}
            maxItems={field.maxItems}
            addLabel={field.addLabel || 'Add Item'}
            disabled={field.disabled}
          />
          {field.description && (
            <FieldDescription>{field.description}</FieldDescription>
          )}
        </div>
      )

    default:
      console.warn(`DynamicField: Unknown field type "${field.type}" for field "${field.id}"`)
      return null
  }
}

DynamicField.propTypes = {
  field: PropTypes.shape({
    id: PropTypes.string.isRequired,
    type: PropTypes.oneOf([
      'text',
      'textarea',
      'number',
      'select',
      'multi-select',
      'switch',
      'checkbox',
      'repeater',
      'password',
    ]).isRequired,
    label: PropTypes.string,
    description: PropTypes.string,
    default: PropTypes.any,
    isPro: PropTypes.bool,
    placeholder: PropTypes.string,
    required: PropTypes.bool,
    disabled: PropTypes.bool,
    options: PropTypes.arrayOf(
      PropTypes.shape({
        value: PropTypes.string.isRequired,
        label: PropTypes.string.isRequired,
      })
    ),
    fields: PropTypes.array, // For repeater
    maxItems: PropTypes.number, // For repeater
    addLabel: PropTypes.string, // For repeater
    rows: PropTypes.number, // For textarea
    conditions: PropTypes.arrayOf(
      PropTypes.shape({
        field: PropTypes.string.isRequired,
        operator: PropTypes.string,
        value: PropTypes.any,
      })
    ),
    validation: PropTypes.shape({
      min: PropTypes.number,
      max: PropTypes.number,
      minLength: PropTypes.number,
      maxLength: PropTypes.number,
      pattern: PropTypes.string,
      required: PropTypes.bool,
      type: PropTypes.string,
    }),
    target: PropTypes.shape({
      page: PropTypes.string.isRequired,
      section: PropTypes.string,
      priority: PropTypes.number,
    }),
  }).isRequired,
}

export default DynamicField
