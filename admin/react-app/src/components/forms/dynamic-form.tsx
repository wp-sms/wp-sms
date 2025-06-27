"use client"

import React from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Checkbox } from "@/components/ui/checkbox"
import { Button } from "@/components/ui/button"
import { Loader2, AlertCircle, CheckCircle, ExternalLink, HelpCircle } from "lucide-react"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Separator } from "@/components/ui/separator"
import { Badge } from "@/components/ui/badge"
import { SearchableSelect } from "./searchable-select"
import { SearchableMultiSelect } from "./searchable-multiselect"
import { HtmlDescription } from "./html-description"
import { SectionCard } from "./section-card"
import { TagBadge } from "./tag-badge"
import { useFormChanges } from "@/hooks/use-form-changes"
import { settingsApi, ValidationError } from "@/services/settings-api"

interface FieldOption {
  [key: string]: string | { [key: string]: string }
}

interface SchemaField {
  key: string
  type: string
  label: string
  description: string
  default: any
  groupLabel: string
  section: string | null
  options: FieldOption | any[]
  order: number
  doc: string
  showIf: { [key: string]: string } | null
  hideIf: { [key: string]: string } | null
  repeatable: boolean
  tag?: string
  readonly?: boolean
  options_depends_on?: string
  sortable?: boolean
}

interface SchemaSection {
  id: string
  title: string
  subtitle: string
  helpUrl: string
  tag?: string
  order: number
  fields: SchemaField[]
  readonly?: boolean
  layout: string
}

interface GroupSchema {
  label: string
  icon: string
  sections: SchemaSection[]
}

interface DynamicFormProps {
  schema: GroupSchema | null
  savedValues: Record<string, any> | null
  loading: boolean
  error: string | null
  onSaveSuccess?: (savedKeys: string[]) => void
}

// Utility to evaluate showIf/hideIf conditions
function shouldFieldBeVisible(field: SchemaField, formData: Record<string, any>): boolean {
  // If showIf is set, all conditions must match
  if (field.showIf) {
    for (const [depKey, depValue] of Object.entries(field.showIf)) {
      if (formData[depKey] !== depValue) {
        return false
      }
    }
  }
  // If hideIf is set, any match hides the field
  if (field.hideIf) {
    for (const [depKey, depValue] of Object.entries(field.hideIf)) {
      if (formData[depKey] === depValue) {
        return false
      }
    }
  }
  return true
}

// Helper to filter options dynamically
function getDynamicOptions(field: SchemaField, formData: Record<string, any>): FieldOption | any[] {
  if (!field.options_depends_on) return field.options;
  const depKey = field.options_depends_on;
  const depValue = formData[depKey];
  if (!depValue || depValue.length === 0) return field.options;
  // If options is an array of objects [{value, label}], filter by value
  if (Array.isArray(field.options)) {
    return field.options.filter((opt: any) => depValue.includes(opt.value ?? Object.keys(opt)[0]));
  }
  // If options is an object {value: label}, filter keys
  const filtered: FieldOption = {};
  Object.entries(field.options).forEach(([k, v]) => {
    if (depValue.includes(k)) filtered[k] = v;
  });
  return filtered;
}

export function DynamicForm({ schema, savedValues, loading, error, onSaveSuccess }: DynamicFormProps) {
  const [formData, setFormData] = React.useState<Record<string, any>>({})
  const [saveLoading, setSaveLoading] = React.useState(false)
  const [saveError, setSaveError] = React.useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = React.useState<Record<string, string>>({})
  const [saveSuccess, setSaveSuccess] = React.useState(false)

  const { updateValue, getChangedFields, hasChanges, resetChanges } = useFormChanges(savedValues)

  // Initialize form data with saved values or defaults when schema loads
  React.useEffect(() => {
    if (schema) {
      const initialData: Record<string, any> = {}
      schema.sections.forEach(section => {
        section.fields.forEach(field => {
          // Use saved value if available, otherwise use default
          initialData[field.key] = savedValues?.[field.key] ?? field.default
        })
      })
      setFormData(initialData)
    }
  }, [schema, savedValues])

  const handleFieldChange = (key: string, value: any) => {
    setFormData(prev => ({
      ...prev,
      [key]: value
    }))
    updateValue(key, value)
    setSaveError(null)
    setFieldErrors(prev => {
      const newErrors = { ...prev }
      delete newErrors[key]
      return newErrors
    })
    setSaveSuccess(false)
  }

  const handleSave = async () => {
    if (!hasChanges()) {
      return
    }

    setSaveLoading(true)
    setSaveError(null)
    setFieldErrors({})
    setSaveSuccess(false)

    try {
      const changedFields = getChangedFields()
      const response = await settingsApi.saveSettings(changedFields)
      
      if (response.success) {
        setSaveSuccess(true)
        resetChanges()
        if (onSaveSuccess) {
          onSaveSuccess(Object.keys(changedFields))
        }
        // Auto scroll to top after successful save
        window.scrollTo({ top: 0, behavior: 'smooth' })
      }
    } catch (error) {
      if (error instanceof ValidationError) {
        setFieldErrors(error.fields)
        setSaveError(error.message)
      } else {
        setSaveError(error instanceof Error ? error.message : 'Failed to save settings')
      }
    } finally {
      setSaveLoading(false)
    }
  }

  const handleReset = () => {
    if (schema) {
      const initialData: Record<string, any> = {}
      schema.sections.forEach(section => {
        section.fields.forEach(field => {
          initialData[field.key] = savedValues?.[field.key] ?? field.default
        })
      })
      setFormData(initialData)
      resetChanges()
      setSaveError(null)
      setFieldErrors({})
      setSaveSuccess(false)
    }
  }

  const renderFieldError = (fieldKey: string) => {
    const error = fieldErrors[fieldKey]
    if (!error) return null

    return (
      <p className="text-sm text-destructive mt-1">
        {error}
      </p>
    )
  }

  const renderField = (field: SchemaField) => {
    // Evaluate showIf/hideIf before rendering
    if (!shouldFieldBeVisible(field, formData)) return null;

    const { key, type, label, description, options, tag, readonly } = field

    const fieldContent = (() => {
      switch (type) {
        case 'header':
          return (
            <div className="space-y-2">
              <Separator className="my-4" />
              <h3 className="text-lg font-semibold">{label}</h3>
              {description && (
                <HtmlDescription content={description} />
              )}
            </div>
          )

        case 'notice':
          return (
            <Alert className="border-blue-200 bg-blue-50 text-blue-800">
              <AlertCircle className="h-4 w-4 text-blue-600" />
              <AlertDescription className="text-blue-800">
                <div className="font-medium">{label}</div>
                {description && (
                  <div className="mt-1 text-sm" dangerouslySetInnerHTML={{ __html: description }} />
                )}
              </AlertDescription>
            </Alert>
          )

        case 'text':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <Input
                id={key}
                value={formData[key] || ''}
                onChange={(e) => handleFieldChange(key, e.target.value)}
                disabled={readonly}
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'textarea':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <Textarea
                id={key}
                value={formData[key] || ''}
                onChange={(e) => handleFieldChange(key, e.target.value)}
                disabled={readonly}
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'number':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <Input
                id={key}
                type="number"
                value={formData[key] || ''}
                onChange={(e) => handleFieldChange(key, e.target.value)}
                placeholder={description}
                disabled={readonly}
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'checkbox':
          return (
            <div className="flex items-center space-x-2">
              <Checkbox
                id={key}
                checked={formData[key] || false}
                onCheckedChange={(checked) => handleFieldChange(key, checked)}
                disabled={readonly}
              />
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'select':
          const selectOptions = getDynamicOptions(field, formData);
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <SearchableSelect
                options={selectOptions}
                value={formData[key] || ''}
                onValueChange={(value) => handleFieldChange(key, value)}
                placeholder="Select an option"
                searchPlaceholder="Search options..."
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'advancedselect':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <SearchableSelect
                options={getDynamicOptions(field, formData) as any[]}
                value={formData[key] || ''}
                onValueChange={(value) => handleFieldChange(key, value)}
                placeholder="Select an option"
                searchPlaceholder="Search options..."
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'countryselect':
          return (
            <div key={key} className="space-y-2">
              <Label htmlFor={key}>{label}</Label>
              <SearchableSelect
                options={getDynamicOptions(field, formData) as any[]}
                value={formData[key] || ''}
                onValueChange={(value) => handleFieldChange(key, value)}
                placeholder="Select a country"
                searchPlaceholder="Search countries..."
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'multiselect':
          const multiOptions = getDynamicOptions(field, formData);
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <SearchableMultiSelect
                options={multiOptions}
                value={Array.isArray(formData[key]) ? formData[key] : []}
                onValueChange={(value) => handleFieldChange(key, value)}
                placeholder="Select options"
                searchPlaceholder="Search options..."
                sortable={field.sortable}
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'advancedmultiselect':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <SearchableMultiSelect
                options={getDynamicOptions(field, formData) as any[]}
                value={Array.isArray(formData[key]) ? formData[key] : []}
                onValueChange={(value) => handleFieldChange(key, value)}
                placeholder="Select options"
                searchPlaceholder="Search options..."
                sortable={field.sortable}
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'html':
          return (
            <div className="space-y-2">
              {label && (
                <div className="flex items-center gap-2">
                  <Label htmlFor={key}>{label}</Label>
                  {tag && <TagBadge tag={tag} />}
                </div>
              )}
              <div 
                className="[&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-xs [&_br]:block [&_br]:mb-2"
                dangerouslySetInnerHTML={{ __html: formData[key] || '' }}
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        default:
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <Input
                id={key}
                value={formData[key] || ''}
                onChange={(e) => handleFieldChange(key, e.target.value)}
                placeholder={`Unsupported field type: ${type}`}
                disabled
              />
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )
      }
    })()

    return <div key={key}>{fieldContent}</div>
  }

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Loading...</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-center p-8">
            <Loader2 className="h-8 w-8 animate-spin" />
          </div>
        </CardContent>
      </Card>
    )
  }

  if (error) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Error</CardTitle>
        </CardHeader>
        <CardContent>
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    )
  }

  if (!schema) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>No Settings Selected</CardTitle>
          <CardDescription>
            Please select a settings group from the sidebar to view its configuration options.
          </CardDescription>
        </CardHeader>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{schema.label}</CardTitle>
        <CardDescription>
          Configure settings for {schema.label.toLowerCase()}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); handleSave(); }}>
          {/* Save Status Messages - Positioned at top */}
          {saveError && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{saveError}</AlertDescription>
            </Alert>
          )}
          
          {saveSuccess && (
            <Alert className="border-green-200 bg-green-50 text-green-800">
              <CheckCircle className="h-4 w-4 text-green-600" />
              <AlertDescription className="text-green-800">Settings saved successfully!</AlertDescription>
            </Alert>
          )}
          
          {schema.sections
            .sort((a, b) => a.order - b.order)
            .map(section => (
              <SectionCard key={section.id} section={section}>
                {section.fields
                  .sort((a, b) => a.order - b.order)
                  .map(renderField)}
              </SectionCard>
            ))}
          
          {/* Success message at bottom as well */}
          {saveSuccess && (
            <Alert className="border-green-200 bg-green-50 text-green-800">
              <CheckCircle className="h-4 w-4 text-green-600" />
              <AlertDescription className="text-green-800">Settings saved successfully!</AlertDescription>
            </Alert>
          )}
          
          <div className="flex justify-end space-x-2 pt-6">
            <Button 
              type="button" 
              variant="outline" 
              onClick={handleReset}
              disabled={saveLoading || !hasChanges()}
            >
              Reset
            </Button>
            <Button 
              type="submit"
              disabled={saveLoading || !hasChanges()}
            >
              {saveLoading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Saving...
                </>
              ) : (
                'Save Changes'
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
} 