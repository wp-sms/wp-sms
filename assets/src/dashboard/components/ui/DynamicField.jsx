import React, { useMemo, useCallback } from 'react'
import PropTypes from 'prop-types'
import { InputField, TextareaField, SelectField, SwitchField, FieldDescription } from './form-field'
import { TemplateTextarea } from '@/components/shared/TemplateTextarea'
import { MultiSelect } from './multi-select'
import { Repeater } from './repeater'
import { Label } from './label'
import { Input } from './input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './select'
import { useSettings } from '@/context/SettingsContext'
import { getWpSettings, __ } from '@/lib/utils'

/**
 * Resolve options reference from add-on data
 *
 * When a field's options is a string (e.g., 'userRoles'), this function
 * looks up the actual options array from the add-on's data object.
 *
 * @param {string|Array} options - Options array or string reference
 * @param {string} addonSlug - The add-on slug to look up data from
 * @returns {Array} Resolved options array
 */
function resolveOptions(options, addonSlug) {
  // If already an array, return as-is
  if (Array.isArray(options)) {
    return options
  }

  // If not a string reference, return empty array
  if (typeof options !== 'string') {
    return []
  }

  // Look up the reference in add-on data
  const { addonSettings = {} } = getWpSettings()
  const addonSchema = addonSettings[addonSlug]

  if (addonSchema?.data && addonSchema.data[options]) {
    return addonSchema.data[options]
  }

  // Fallback: check global wpSmsSettings for common data keys
  const wpSettings = getWpSettings()
  if (wpSettings[options]) {
    // Handle WordPress roles format { role_key: 'Role Name' }
    const data = wpSettings[options]
    if (typeof data === 'object' && !Array.isArray(data)) {
      return Object.entries(data).map(([value, label]) => ({ value, label }))
    }
    return data
  }

  return []
}

/**
 * Resolve variables for template textarea fields
 *
 * @param {string[]|string|undefined} variables - Variables array, string reference to addon data, or undefined
 * @param {string} addonSlug - The add-on slug to look up data from
 * @returns {string[]} Resolved variables array
 */
function resolveVariables(variables, addonSlug) {
  if (Array.isArray(variables)) {
    return variables
  }

  if (typeof variables !== 'string') {
    return []
  }

  // Look up the reference in add-on data
  const { addonSettings = {} } = getWpSettings()
  const addonSchema = addonSettings[addonSlug]

  if (addonSchema?.data && Array.isArray(addonSchema.data[variables])) {
    return addonSchema.data[variables]
  }

  return []
}

/**
 * Evaluate conditional display rules
 *
 * @param {Array} conditions - Array of condition objects
 * @param {Object} settings - Current settings values
 * @param {Object} proSettings - Current pro settings values
 * @param {Object} addonValues - Current addon settings values (keyed by addon slug)
 * @returns {boolean} Whether all conditions are met
 */
function evaluateConditions(conditions, settings, proSettings, addonValues) {
  if (!conditions || conditions.length === 0) return true

  return conditions.every(condition => {
    let value

    // Check addon values first (these are updated by React state changes)
    if (addonValues) {
      for (const addonSlug of Object.keys(addonValues)) {
        if (addonValues[addonSlug]?.[condition.field] !== undefined) {
          value = addonValues[addonSlug][condition.field]
          break
        }
      }
    }

    // Fall back to settings, then proSettings
    if (value === undefined) {
      value = settings[condition.field] ?? proSettings[condition.field]
    }

    switch (condition.operator) {
      case '==':
        // Handle legacy string formats (enable/disable, yes/no) when comparing to booleans
        if (condition.value === true) {
          return value === true || value === 'enable' || value === 'yes' || value === 1 || value === '1'
        }
        if (condition.value === false) {
          return value === false || value === 'disable' || value === 'no' || value === 0 || value === '0' || value === '' || value === null || value === undefined
        }
        return value === condition.value
      case '!=':
        if (condition.value === true) {
          return !(value === true || value === 'enable' || value === 'yes' || value === 1 || value === '1')
        }
        if (condition.value === false) {
          return !(value === false || value === 'disable' || value === 'no' || value === 0 || value === '0' || value === '' || value === null || value === undefined)
        }
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
  const {
    settings,
    proSettings,
    addonValues,
    getSetting,
    getProSetting,
    getAddonSetting,
    updateSetting,
    updateProSetting,
    updateAddonSetting,
  } = useSettings()

  // Validate field has required properties
  if (!field || !field.id || !field.type) {
    console.warn('DynamicField: Invalid field configuration', field)
    return null
  }

  // Determine which value source to use based on field properties
  const hasAddonSlug = Boolean(field.addonSlug)

  // Get value from the appropriate source
  const value = useMemo(() => {
    if (hasAddonSlug) {
      return getAddonSetting(field.addonSlug, field.id, field.default ?? '')
    }
    if (field.isPro) {
      return getProSetting(field.id, field.default ?? '')
    }
    return getSetting(field.id, field.default ?? '')
  }, [hasAddonSlug, field.addonSlug, field.id, field.default, field.isPro, getAddonSetting, getProSetting, getSetting])

  // Create setter function based on field source
  const setValue = useCallback((newValue) => {
    if (hasAddonSlug) {
      updateAddonSetting(field.addonSlug, field.id, newValue)
    } else if (field.isPro) {
      updateProSetting(field.id, newValue)
    } else {
      updateSetting(field.id, newValue)
    }
  }, [hasAddonSlug, field.addonSlug, field.id, field.isPro, updateAddonSetting, updateProSetting, updateSetting])

  // Evaluate conditions
  const isVisible = useMemo(() => {
    return evaluateConditions(field.conditions, settings || {}, proSettings || {}, addonValues || {})
  }, [field.conditions, settings, proSettings, addonValues])

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

    case 'textarea': {
      // Resolve variables: can be an array or a string reference to addon data
      const variables = resolveVariables(field.variables, field.addonSlug)

      if (variables.length > 0) {
        return (
          <div className="wsms-space-y-2">
            {field.label && (
              <Label className="wsms-text-[13px] wsms-font-medium">
                {field.label}
                {field.required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
              </Label>
            )}
            <TemplateTextarea
              value={value || ''}
              onChange={setValue}
              variables={variables}
              placeholder={field.placeholder}
              rows={field.rows || 3}
            />
            {field.description && (
              <FieldDescription>{field.description}</FieldDescription>
            )}
          </div>
        )
      }

      return (
        <TextareaField
          {...commonProps}
          value={value || ''}
          onChange={(e) => setValue(e.target.value)}
          placeholder={field.placeholder}
          rows={field.rows || 3}
        />
      )
    }

    case 'select':
      return (
        <SelectField
          {...commonProps}
          value={value || ''}
          onValueChange={setValue}
          placeholder={field.placeholder || 'Select an option...'}
          options={resolveOptions(field.options, field.addonSlug)}
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
            options={resolveOptions(field.options, field.addonSlug)}
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

    case 'time-duration': {
      const dur = (typeof value === 'object' && value !== null) ? value : { days: 0, hours: 0, minutes: 0 }
      const updateDur = (key, val) => setValue({ ...dur, [key]: val })
      return (
        <div className="wsms-space-y-2">
          {field.label && (
            <Label className="wsms-text-[13px] wsms-font-medium">
              {field.label}
              {field.required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
            </Label>
          )}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-items-center wsms-gap-1.5">
              <Input
                type="number"
                min={0}
                value={dur.days ?? 0}
                onChange={(e) => updateDur('days', parseInt(e.target.value) || 0)}
                className="wsms-w-[70px]"
                disabled={field.disabled}
              />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('Days')}</span>
            </div>
            <div className="wsms-flex wsms-items-center wsms-gap-1.5">
              <Input
                type="number"
                min={0}
                max={23}
                value={dur.hours ?? 0}
                onChange={(e) => updateDur('hours', parseInt(e.target.value) || 0)}
                className="wsms-w-[70px]"
                disabled={field.disabled}
              />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('Hours')}</span>
            </div>
            <div className="wsms-flex wsms-items-center wsms-gap-1.5">
              <Input
                type="number"
                min={0}
                max={59}
                step={5}
                value={dur.minutes ?? 0}
                onChange={(e) => updateDur('minutes', parseInt(e.target.value) || 0)}
                className="wsms-w-[70px]"
                disabled={field.disabled}
              />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('Minutes')}</span>
            </div>
          </div>
          {field.description && (
            <FieldDescription>{field.description}</FieldDescription>
          )}
        </div>
      )
    }

    case 'coupon-amount': {
      const coupon = (typeof value === 'object' && value !== null) ? value : { amount: 0, type: 'percent' }
      const updateCoupon = (key, val) => setValue({ ...coupon, [key]: val })
      const typeOptions = resolveOptions(field.options, field.addonSlug)
      return (
        <div className="wsms-space-y-2">
          {field.label && (
            <Label className="wsms-text-[13px] wsms-font-medium">
              {field.label}
              {field.required && <span className="wsms-ml-1 wsms-text-destructive">*</span>}
            </Label>
          )}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <Input
              type="number"
              min={0}
              value={coupon.amount ?? 0}
              onChange={(e) => updateCoupon('amount', parseFloat(e.target.value) || 0)}
              className="wsms-w-[100px]"
              disabled={field.disabled}
            />
            <Select
              value={coupon.type || 'percent'}
              onValueChange={(val) => updateCoupon('type', val)}
              disabled={field.disabled}
            >
              <SelectTrigger className="wsms-w-[140px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {typeOptions.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          {field.description && (
            <FieldDescription>{field.description}</FieldDescription>
          )}
        </div>
      )
    }

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
      'time-duration',
      'coupon-amount',
    ]).isRequired,
    label: PropTypes.string,
    description: PropTypes.string,
    default: PropTypes.any,
    isPro: PropTypes.bool,
    placeholder: PropTypes.string,
    required: PropTypes.bool,
    disabled: PropTypes.bool,
    addonSlug: PropTypes.string, // Add-on slug for resolving data references
    options: PropTypes.oneOfType([
      PropTypes.string, // String reference to data key (e.g., 'userRoles')
      PropTypes.arrayOf(
        PropTypes.shape({
          value: PropTypes.string.isRequired,
          label: PropTypes.string.isRequired,
        })
      ),
    ]),
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
