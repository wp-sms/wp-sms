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
import { GatewayFields } from "./gateway-fields"
import { useFormChanges } from "@/hooks/use-form-changes"
import { settingsApi, ValidationError } from "@/services/settings-api"
import { RepeaterField } from "./repeater-field"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

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
  placeholder?: string
  fieldGroups?: any[]
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

// Custom hook for WordPress media uploader
function useWordPressMediaUploader() {
  const openMediaUploader = (callback: (url: string) => void) => {
    // Check if wp.media is available (WordPress media uploader)
    if (typeof window !== 'undefined' && (window as any).wp && (window as any).wp.media) {
      try {
        const mediaUploader = (window as any).wp.media({
          title: 'Select Image',
          button: {
            text: 'Use this image'
          },
          multiple: false
        })

        mediaUploader.on('select', () => {
          const attachment = mediaUploader.state().get('selection').first().toJSON()
          callback(attachment.url)
        })

        mediaUploader.open()
      } catch (error) {
        console.error('Error opening WordPress media uploader:', error)
        // Fallback to file input
        openFileInput(callback)
      }
    } else {
      console.warn('WordPress media uploader not available, using fallback')
      // Fallback: create a file input
      openFileInput(callback)
    }
  }

  const openFileInput = (callback: (url: string) => void) => {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.style.display = 'none'
    
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) {
        // Create a temporary URL for the file
        const url = URL.createObjectURL(file)
        callback(url)
        
        // Clean up the temporary URL after a delay
        setTimeout(() => URL.revokeObjectURL(url), 1000)
      }
      
      // Clean up the input
      if (document.body.contains(input)) {
        document.body.removeChild(input)
      }
    }
    
    document.body.appendChild(input)
    input.click()
  }

  return { openMediaUploader }
}

export function DynamicForm({ schema, savedValues, loading, error, onSaveSuccess }: DynamicFormProps) {
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
          initialData[field.key] = savedValues?.[field.key] ?? field.default
        })
      })
      
      // Also include any gateway fields that might be in savedValues
      if (savedValues) {
        Object.keys(savedValues).forEach(key => {
          // If this key is not already in initialData (i.e., it's a gateway field)
          if (!(key in initialData)) {
            initialData[key] = savedValues[key]
          }
        })
      }
      
      setFormData(initialData)
    }
  }, [schema, savedValues])

  // Hide notices when switching groups
  React.useEffect(() => {
    setSaveSuccess(false);
    setSaveError(null);
    setFieldErrors({});
  }, [schema]);

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
      
      // Initialize with schema fields
      schema.sections.forEach(section => {
        section.fields.forEach(field => {
          initialData[field.key] = savedValues?.[field.key] ?? field.default
        })
      })
      
      // Also include any gateway fields that might be in savedValues
      if (savedValues) {
        Object.keys(savedValues).forEach(key => {
          // If this key is not already in initialData (i.e., it's a gateway field)
          if (!(key in initialData)) {
            initialData[key] = savedValues[key]
          }
        })
      }
      
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
            <div className={`space-y-2 ${readonly ? 'opacity-50' : ''}`}>
              <div className="flex items-center gap-2">
                <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
                {readonly && (
                  <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
                    Read Only
                  </Badge>
                )}
                {tag && <TagBadge tag={tag} />}
              </div>
              <Input
                id={key}
                value={formData[key] || ''}
                onChange={(e) => handleFieldChange(key, e.target.value)}
                disabled={readonly}
              />
              {description && (
                <div className={readonly ? 'opacity-70' : ''}>
                  <HtmlDescription content={description} />
                </div>
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'textarea':
          return (
            <div className={`space-y-2 ${readonly ? 'opacity-50' : ''}`}>
              <div className="flex items-center gap-2">
                <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
                {readonly && (
                  <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
                    Read Only
                  </Badge>
                )}
                {tag && <TagBadge tag={tag} />}
              </div>
              <Textarea
                id={key}
                value={formData[key] || ''}
                onChange={(e) => handleFieldChange(key, e.target.value)}
                disabled={readonly}
              />
              {description && (
                <div className={readonly ? 'opacity-70' : ''}>
                  <HtmlDescription content={description} />
                </div>
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'number':
          return (
            <div className={`space-y-2 ${readonly ? 'opacity-50' : ''}`}>
              <div className="flex items-center gap-2">
                <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
                {readonly && (
                  <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
                    Read Only
                  </Badge>
                )}
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
                <div className={readonly ? 'opacity-70' : ''}>
                  <HtmlDescription content={description} />
                </div>
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'checkbox':
          return (
            <div className={`flex items-center space-x-2 ${readonly ? 'opacity-50' : ''}`}>
              <Checkbox
                id={key}
                checked={formData[key] || false}
                onCheckedChange={(checked) => handleFieldChange(key, checked)}
                disabled={readonly}
              />
              <div className="flex items-center gap-2">
                <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
                {readonly && (
                  <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
                    Read Only
                  </Badge>
                )}
                {tag && <TagBadge tag={tag} />}
              </div>
              {description && (
                <div className={readonly ? 'opacity-70' : ''}>
                  <HtmlDescription content={description} />
                </div>
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'select':
          const selectOptions = getDynamicOptions(field, formData);
          return (
            <div className={`space-y-2 ${readonly ? 'opacity-50' : ''}`}>
              <div className="flex items-center gap-2">
                <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
                {readonly && (
                  <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
                    Read Only
                  </Badge>
                )}
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
                <div className={readonly ? 'opacity-70' : ''}>
                  <HtmlDescription content={description} />
                </div>
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'advancedselect':
          return (
            <div className={`space-y-2 ${readonly ? 'opacity-50' : ''}`}>
              <div className="flex items-center gap-2">
                <Label htmlFor={key} className={readonly ? 'text-muted-foreground' : ''}>{label}</Label>
                {readonly && (
                  <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
                    Read Only
                  </Badge>
                )}
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
                <div className={readonly ? 'opacity-70' : ''}>
                  <HtmlDescription content={description} />
                </div>
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

        case 'repeater':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <RepeaterField
                label=""
                description=""
                value={Array.isArray(formData[key]) ? formData[key] : []}
                onChange={(value) => handleFieldChange(key, value)}
                addButtonText={`Add ${label}`}
              >
                {(index, onUpdate, onRemove) => {
                  const itemData = formData[key]?.[index] || {}
                  const fieldGroups = field.fieldGroups || []
                  
                  return (
                    <div className="space-y-4">
                      {fieldGroups.map((group: any) => (
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
                            {group.fields.map((groupField: any) => {
                              const fieldKey = `${key}[${index}][${groupField.key}]`
                              const fieldValue = itemData[groupField.key] || ''
                              
                              return (
                                <div key={groupField.key} className="space-y-2">
                                  <Label htmlFor={fieldKey}>{groupField.label}</Label>
                                  {groupField.type === 'text' && (
                                    <Input
                                      id={fieldKey}
                                      value={fieldValue}
                                      onChange={(e) => {
                                        const newData = { ...itemData, [groupField.key]: e.target.value }
                                        onUpdate(newData)
                                      }}
                                      placeholder={groupField.placeholder || ''}
                                    />
                                  )}
                                  {groupField.type === 'select' && (
                                    <Select
                                      value={fieldValue}
                                      onValueChange={(value) => {
                                        const newData = { ...itemData, [groupField.key]: value }
                                        onUpdate(newData)
                                      }}
                                    >
                                      <SelectTrigger>
                                        <SelectValue placeholder={groupField.placeholder || 'Select an option'} />
                                      </SelectTrigger>
                                      <SelectContent>
                                        {Object.entries(groupField.options || {}).map(([value, label]) => (
                                          <SelectItem key={value} value={value}>
                                            {label as string}
                                          </SelectItem>
                                        ))}
                                      </SelectContent>
                                    </Select>
                                  )}
                                  {groupField.type === 'image' && (
                                    <div className="space-y-2">
                                      <div className="flex items-center gap-2">
                                        <Input
                                          id={fieldKey}
                                          value={fieldValue}
                                          onChange={(e) => {
                                            const newData = { ...itemData, [groupField.key]: e.target.value }
                                            onUpdate(newData)
                                          }}
                                          placeholder={groupField.placeholder || 'Image URL'}
                                        />
                                        <Button
                                          type="button"
                                          variant="outline"
                                          size="sm"
                                          onClick={() => {
                                            openMediaUploader((url) => {
                                              const newData = { ...itemData, [groupField.key]: url }
                                              onUpdate(newData)
                                            })
                                          }}
                                        >
                                          Upload
                                        </Button>
                                      </div>
                                      {fieldValue && (
                                        <div className="mt-2">
                                          <img 
                                            src={fieldValue} 
                                            alt="Preview" 
                                            className="max-w-full h-20 object-cover rounded border"
                                            onError={(e) => {
                                              e.currentTarget.style.display = 'none'
                                            }}
                                          />
                                        </div>
                                      )}
                                    </div>
                                  )}
                                  {groupField.description && (
                                    <p className="text-xs text-muted-foreground">{groupField.description}</p>
                                  )}
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
              {description && (
                <HtmlDescription content={description} />
              )}
              {renderFieldError(key)}
            </div>
          )

        case 'color':
          return (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label htmlFor={key}>{label}</Label>
                {tag && <TagBadge tag={tag} />}
              </div>
              <div className="flex items-center gap-2">
                <Input
                  type="color"
                  value={formData[key] || '#ff6b35'}
                  onChange={(e) => handleFieldChange(key, e.target.value)}
                  disabled={readonly}
                  className="w-12 h-10 p-1 border rounded cursor-pointer"
                />
                <Input
                  type="text"
                  value={formData[key] || ''}
                  onChange={(e) => handleFieldChange(key, e.target.value)}
                  disabled={readonly}
                  className="flex-1"
                  placeholder="#ff6b35"
                />
              </div>
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

  // Check if this is the gateway group
  const isGatewayGroup = schema.label.toLowerCase().includes('gateway') || 
                        schema.sections.some(section => 
                          section.title.toLowerCase().includes('gateway') ||
                          section.fields.some(field => field.key === 'gateway_name')
                        )

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
                  .map((field, index) => {
                    const fieldElement = renderField(field)
                    
                    // For gateway group, insert gateway fields after the gateway_help field
                    if (isGatewayGroup && 
                        section.id === 'sms_gateway_setup' && 
                        field.key === 'gateway_help') {
                      return (
                        <React.Fragment key={field.key}>
                          {fieldElement}
                          <GatewayFields
                            gatewayName={formData.gateway_name}
                            formData={formData}
                            onFieldChange={handleFieldChange}
                          />
                        </React.Fragment>
                      )
                    }
                    
                    return fieldElement
                  })}
              </SectionCard>
            ))}
          
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