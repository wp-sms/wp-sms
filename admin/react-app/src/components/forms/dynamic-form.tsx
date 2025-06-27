"use client"

import React from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Checkbox } from "@/components/ui/checkbox"
import { Button } from "@/components/ui/button"
import { Loader2, AlertCircle, CheckCircle } from "lucide-react"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Separator } from "@/components/ui/separator"
import { SearchableSelect } from "./searchable-select"
import { SearchableMultiSelect } from "./searchable-multiselect"
import { HtmlDescription } from "./html-description"
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
  options: FieldOption
  order: number
  doc: string
  showIf: { [key: string]: string } | null
  hideIf: { [key: string]: string } | null
  repeatable: boolean
}

interface GroupSchema {
  label: string
  fields: SchemaField[]
}

interface DynamicFormProps {
  schema: GroupSchema | null
  savedValues: Record<string, any> | null
  loading: boolean
  error: string | null
  onSaveSuccess?: (savedKeys: string[]) => void
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
      schema.fields.forEach(field => {
        // Use saved value if available, otherwise use default
        initialData[field.key] = savedValues?.[field.key] ?? field.default
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
      schema.fields.forEach(field => {
        initialData[field.key] = savedValues?.[field.key] ?? field.default
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
    const { key, type, label, description, options } = field

    switch (type) {
      case 'header':
        return (
          <div key={key} className="space-y-2">
            <Separator className="my-4" />
            <h3 className="text-lg font-semibold">{label}</h3>
            {description && (
              <HtmlDescription content={description} />
            )}
          </div>
        )

      case 'text':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Input
              id={key}
              value={formData[key] || ''}
              onChange={(e) => handleFieldChange(key, e.target.value)}
            />
            {description && (
              <HtmlDescription content={description} />
            )}
            {renderFieldError(key)}
          </div>
        )

      case 'textarea':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Textarea
              id={key}
              value={formData[key] || ''}
              onChange={(e) => handleFieldChange(key, e.target.value)}
            />
            {description && (
              <HtmlDescription content={description} />
            )}
            {renderFieldError(key)}
          </div>
        )

      case 'number':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Input
              id={key}
              type="number"
              value={formData[key] || ''}
              onChange={(e) => handleFieldChange(key, e.target.value)}
              placeholder={description}
            />
            {description && (
              <HtmlDescription content={description} />
            )}
            {renderFieldError(key)}
          </div>
        )

      case 'checkbox':
        return (
          <div key={key} className="flex items-center space-x-2">
            <Checkbox
              id={key}
              checked={formData[key] || false}
              onCheckedChange={(checked) => handleFieldChange(key, checked)}
            />
            <Label htmlFor={key}>{label}</Label>
            {description && (
              <HtmlDescription content={description} className="ml-6" />
            )}
            {renderFieldError(key)}
          </div>
        )

      case 'select':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <SearchableSelect
              options={options}
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
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <SearchableSelect
              options={options}
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
              options={options}
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
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <SearchableMultiSelect
              options={options}
              value={Array.isArray(formData[key]) ? formData[key] : []}
              onValueChange={(value) => handleFieldChange(key, value)}
              placeholder="Select options"
              searchPlaceholder="Search options..."
            />
            {description && (
              <HtmlDescription content={description} />
            )}
            {renderFieldError(key)}
          </div>
        )

      case 'advancedmultiselect':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <SearchableMultiSelect
              options={options}
              value={Array.isArray(formData[key]) ? formData[key] : []}
              onValueChange={(value) => handleFieldChange(key, value)}
              placeholder="Select options"
              searchPlaceholder="Search options..."
            />
            {description && (
              <HtmlDescription content={description} />
            )}
            {renderFieldError(key)}
          </div>
        )

      case 'html':
        return (
          <div key={key} className="space-y-2">
            {label && <Label htmlFor={key}>{label}</Label>}
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
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
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
          
          {schema.fields
            .sort((a, b) => a.order - b.order)
            .map(renderField)}
          
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