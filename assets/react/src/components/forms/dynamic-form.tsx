"use client"

import React from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Loader2, AlertCircle, CheckCircle, HelpCircle } from "lucide-react"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { SectionCard } from "./section-card"
import { FieldRenderer } from "./field-renderer"
import { useFormChanges } from "@/hooks/use-form-changes"
import { settingsApi, ValidationError } from "@/services/settings-api"
import { useWordPressMediaUploader } from "./hooks/use-wordpress-media-uploader"
import { shouldFieldBeVisible, getDynamicOptions } from "./utils"
import { DynamicFormProps, SchemaField } from "./types"
import * as LucideIcons from "lucide-react"

export function DynamicForm({ schema, savedValues, loading, error, onSaveSuccess, onSchemaRefresh, onValuesRefresh }: DynamicFormProps) {
  const [formData, setFormData] = React.useState<Record<string, any>>({})
  const [saveLoading, setSaveLoading] = React.useState(false)
  const [saveError, setSaveError] = React.useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = React.useState<Record<string, string>>({})
  const [saveSuccess, setSaveSuccess] = React.useState(false)

  const { updateValue, getChangedFields, hasChanges, resetChanges } = useFormChanges(savedValues)
  const { openMediaUploader } = useWordPressMediaUploader()

  // Initialize form data with saved values or defaults when schema loads
  React.useEffect(() => {
    if (schema) {
      const initialData: Record<string, any> = {}
      
      // Initialize with schema fields
      schema.sections.forEach(section => {
        section.fields.forEach(field => {
          // Use saved value if available, otherwise use default
          if (savedValues && savedValues.hasOwnProperty(field.key)) {
            initialData[field.key] = savedValues[field.key]
          } else if (field.default !== undefined) {
            initialData[field.key] = field.default
          } else {
            // Set appropriate defaults based on field type
            switch (field.type) {
              case 'checkbox':
                initialData[field.key] = false
                break
              case 'multiselect':
              case 'advancedmultiselect':
                initialData[field.key] = []
                break
              default:
                initialData[field.key] = ''
            }
          }
        })
      })
      
      setFormData(initialData)
      resetChanges()
    }
  }, [schema, savedValues, resetChanges])

  const handleFieldChange = (key: string, value: any) => {
    setFormData(prev => ({ ...prev, [key]: value }))
    updateValue(key, value)
    
    // Handle auto_save_and_refresh fields
    const field = schema?.sections
      .flatMap(section => section.fields)
      .find(field => field.key === key)
    
    if (field?.auto_save_and_refresh) {
      saveFieldAndRefreshSchema(key, value)
    }
    
    // Clean up dependent field values when source field changes
    if (schema) {
      const dependentFields = schema.sections
        .flatMap(section => section.fields)
        .filter(field => field.options_depends_on === key)
      
      dependentFields.forEach(depField => {
        const currentValue = formData[depField.key]
        if (currentValue && Array.isArray(currentValue)) {
          // Get available options for the dependent field
          const availableOptions = getDynamicOptions(depField, { ...formData, [key]: value })
          
          // Filter out values that are no longer available
          let filteredValue: string[]
          if (Array.isArray(availableOptions)) {
            const availableValues = availableOptions.map((opt: any) => opt.value || Object.keys(opt)[0])
            filteredValue = currentValue.filter(v => availableValues.includes(v))
          } else {
            // For object-based options
            const availableValues = Object.keys(availableOptions)
            filteredValue = currentValue.filter(v => availableValues.includes(v))
          }
          
          // Update the dependent field if values were removed
          if (filteredValue.length !== currentValue.length) {
            setFormData(prev => ({ ...prev, [depField.key]: filteredValue }))
            updateValue(depField.key, filteredValue)
          }
        }
      })
    }
    
    // Clear field error when user starts typing
    if (fieldErrors[key]) {
      setFieldErrors(prev => {
        const newErrors = { ...prev }
        delete newErrors[key]
        return newErrors
      })
    }
  }

  const saveFieldAndRefreshSchema = async (key: string, value: any) => {
    try {
      setSaveLoading(true)
      setSaveError(null)
      
      const response = await settingsApi.saveSettings({ [key]: value })
      
      if (response.success) {
        setSaveSuccess(true)
        resetChanges()
        
        // Trigger schema refresh
        onSchemaRefresh?.()
        
        // Trigger values refresh to get updated field values
        onValuesRefresh?.()
        
        // Hide success message after 3 seconds
        setTimeout(() => setSaveSuccess(false), 3000)
      } else {
        setSaveError('Failed to save field and refresh schema')
      }
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'An unexpected error occurred')
    } finally {
      setSaveLoading(false)
    }
  }

  const handleSave = async () => {
    if (!schema) return

    setSaveLoading(true)
    setSaveError(null)
    setFieldErrors({})

    try {
      const changedFields = getChangedFields()
      const changedKeys = Object.keys(changedFields)
      
      if (changedKeys.length === 0) {
        setSaveLoading(false)
        return
      }

      const response = await settingsApi.saveSettings(changedFields)
      
      if (response.success) {
        setSaveSuccess(true)
        setFormData(prev => ({ ...prev, ...changedFields }))
        resetChanges()
        onSaveSuccess?.(changedKeys)
        
        // Hide success message after 3 seconds
        setTimeout(() => setSaveSuccess(false), 3000)
      } else {
        setSaveError('Failed to save settings')
      }
    } catch (error) {
      if (error instanceof ValidationError) {
        setFieldErrors(error.fields)
        setSaveError('Please fix the validation errors below')
      } else {
        setSaveError(error instanceof Error ? error.message : 'An unexpected error occurred')
      }
    } finally {
      setSaveLoading(false)
    }
  }

  const handleReset = () => {
    if (savedValues) {
      setFormData(savedValues)
      resetChanges()
    }
    setFieldErrors({})
    setSaveSuccess(false)
  }

  // Scroll to top after save
  React.useEffect(() => {
    if (saveSuccess) {
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }, [saveSuccess])

  // Warn on unsaved changes
  React.useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      if (hasChanges()) {
        e.preventDefault()
        e.returnValue = ''
        return ''
      }
    }
    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload)
    }
  }, [formData, fieldErrors])

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="h-8 w-8 animate-spin" />
        <span className="ml-2">Loading settings...</span>
      </div>
    )
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    )
  }

  if (!schema) {
    return (
      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>No settings schema available.</AlertDescription>
      </Alert>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="flex items-center justify-center w-10 h-10 bg-primary/10 rounded-lg">
                <span className="text-2xl">
                  {(() => {
                    const IconComponent = (LucideIcons as any)[schema.icon] || LucideIcons["Settings"];
                    return <IconComponent />;
                  })()}
                </span>
              </div>
              <div>
                <CardTitle className="text-xl">{schema.label}</CardTitle>
                <CardDescription>
                  Configure your settings and preferences
                </CardDescription>
              </div>
            </div>
            <div className="flex items-center gap-2">
              {schema.sections.some(section => section.helpUrl) && (
                <Button variant="outline" size="sm" asChild>
                  <a href={schema.sections.find(s => s.helpUrl)?.helpUrl} target="_blank" rel="noopener noreferrer">
                    <HelpCircle className="h-4 w-4 mr-2" />
                    Help
                  </a>
                </Button>
              )}
            </div>
          </div>
        </CardHeader>
      </Card>

      {/* Success/Error Messages */}
      {saveSuccess && (
        <Alert className="border-green-200 bg-green-50">
          <CheckCircle className="h-4 w-4 text-green-600" />
          <AlertDescription className="text-green-800">
            Settings saved successfully!
          </AlertDescription>
        </Alert>
      )}

      {saveError && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{saveError}</AlertDescription>
        </Alert>
      )}

      {/* Sections */}
      <div className="space-y-6">
        {(schema.sections || []).map((section) => (
          <SectionCard key={section.id} section={section}>
            <div className="space-y-6">
              {(section.fields || [])
                .filter((field: SchemaField) => shouldFieldBeVisible(field, formData))
                .map((field: SchemaField) => (
                  <FieldRenderer
                    key={field.key}
                    field={field}
                    value={formData[field.key]}
                    onChange={(value) => handleFieldChange(field.key, value)}
                    error={fieldErrors[field.key]}
                    formData={formData}
                  />
                ))}
            </div>
          </SectionCard>
        ))}
      </div>

      {/* Action Buttons */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Button
                onClick={handleSave}
                disabled={saveLoading || !hasChanges()}
                className="min-w-[100px]"
              >
                {saveLoading ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    Saving...
                  </>
                ) : (
                  'Save Changes'
                )}
              </Button>
              
              <Button
                variant="outline"
                onClick={handleReset}
                disabled={saveLoading || !hasChanges()}
              >
                Reset
              </Button>
            </div>
            
            {hasChanges() && (
              <div className="text-sm text-muted-foreground">
                You have unsaved changes
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
} 