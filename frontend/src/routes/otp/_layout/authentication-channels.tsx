import { useForm } from '@tanstack/react-form'
import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { AlertCircle, Loader2, Save, Settings, X } from 'lucide-react'
import { useState } from 'react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { getSchemaByGroup } from '@/services/settings/get-schema-by-group'
import type { SchemaField } from '@/types/settings/group-schema'

export const Route = createFileRoute('/otp/_layout/authentication-channels')({
  loader: ({ context }) =>
    context.queryClient.ensureQueryData(getSchemaByGroup({ groupName: 'otp-channel', include_hidden: true })),
  component: RouteComponent,
})

function RouteComponent() {
  const { data: result } = useSuspenseQuery(getSchemaByGroup({ groupName: 'otp-channel', include_hidden: true }))
  const groupSchema = result.data.data
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [selectedField, setSelectedField] = useState<SchemaField | null>(null)

  console.log(groupSchema)

  // Create default values from schema
  const createDefaultValues = (schema: unknown) => {
    const defaults: Record<string, unknown> = {}

    if (schema && typeof schema === 'object' && 'sections' in schema) {
      const schemaWithSections = schema as {
        sections: Array<{
          fields: Array<{ key: string; default: unknown; sub_fields?: Array<{ key: string; default: unknown }> }>
        }>
      }

      schemaWithSections.sections.forEach((section) => {
        if (section.fields) {
          section.fields.forEach((field) => {
            if (field.default !== undefined) {
              defaults[field.key] = field.default
            }

            // Handle sub_fields
            if (field.sub_fields) {
              field.sub_fields.forEach((subField) => {
                if (subField.default !== undefined) {
                  defaults[subField.key] = subField.default
                }
              })
            }
          })
        }
      })
    }

    return defaults
  }

  const form = useForm({
    defaultValues: createDefaultValues(groupSchema),
    onSubmit: async ({ value }) => {
      // Add your submission logic here
      await new Promise((resolve) => setTimeout(resolve, 2000)) // Simulate API call

      console.log('Form submitted with values:', value)
    },
  })

  // Helper functions to safely access field properties
  const getFieldOptions = (field: SchemaField | SubField) => {
    return Array.isArray(field.options) ? {} : field.options
  }

  const getFieldPlaceholder = (field: SchemaField | SubField) => {
    return 'placeholder' in field ? field.placeholder : ''
  }

  const getFieldStep = (field: SchemaField | SubField) => {
    return 'step' in field ? field.step : null
  }

  const getFieldRows = (field: SchemaField | SubField) => {
    return 'rows' in field ? field.rows : null
  }

  const getFieldSubFields = (field: SchemaField | SubField) => {
    return 'sub_fields' in field ? field.sub_fields || [] : 'subFields' in field ? field.subFields || [] : []
  }

  const handleOpenSubFields = (field: SchemaField | SubField) => {
    setSelectedField(field)
    setDrawerOpen(true)
  }

  const renderField = (field: SchemaField | SubField, isSubField = false) => {
    return (
      <form.Field
        key={field.key}
        name={field.key}
        children={(fieldApi) => {
          const fieldValue = fieldApi.state.value
          const fieldState = fieldApi.state.meta

          const renderFieldContent = () => {
            switch (field.type) {
              case 'text':
              case 'tel':
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <Input
                      id={field.key}
                      name={fieldApi.name}
                      type={field.type === 'tel' ? 'tel' : 'text'}
                      placeholder={getFieldPlaceholder(field)}
                      value={String(fieldValue || '')}
                      onBlur={fieldApi.handleBlur}
                      onChange={(e) => fieldApi.handleChange(e.target.value)}
                      disabled={field.readonly}
                    />
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )

              case 'textarea':
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <Textarea
                      id={field.key}
                      name={fieldApi.name}
                      placeholder={getFieldPlaceholder(field)}
                      value={String(fieldValue || '')}
                      onBlur={fieldApi.handleBlur}
                      onChange={(e) => fieldApi.handleChange(e.target.value)}
                      disabled={field.readonly}
                      rows={getFieldRows(field) || 3}
                    />
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )

              case 'number':
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <Input
                      id={field.key}
                      name={fieldApi.name}
                      type="number"
                      min={field.min || undefined}
                      max={field.max || undefined}
                      step={getFieldStep(field) || undefined}
                      value={String(fieldValue || '')}
                      onBlur={fieldApi.handleBlur}
                      onChange={(e) => fieldApi.handleChange(parseFloat(e.target.value) || 0)}
                      disabled={field.readonly}
                    />
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )

              case 'select':
              case 'advancedselect':
              case 'countryselect':
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <Select
                      value={String(fieldValue || '')}
                      onValueChange={(value) => fieldApi.handleChange(value)}
                      disabled={field.readonly}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select an option" />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(getFieldOptions(field)).map(([key, value]) => (
                          <SelectItem key={key} value={key}>
                            {typeof value === 'string' ? value : key}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )

              case 'multiselect': {
                const selectedValues = Array.isArray(fieldValue) ? fieldValue : []
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <Select
                      value=""
                      onValueChange={(value) => {
                        if (value && !selectedValues.includes(value)) {
                          fieldApi.handleChange([...selectedValues, value])
                        }
                      }}
                      disabled={field.readonly}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select options" />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(getFieldOptions(field)).map(([key, value]) => (
                          <SelectItem key={key} value={key} disabled={selectedValues.includes(key)}>
                            {typeof value === 'string' ? value : key}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {selectedValues.length > 0 && (
                      <div className="flex flex-wrap gap-2">
                        {selectedValues.map((value) => (
                          <Badge key={value} variant="secondary" className="flex items-center gap-1">
                            {(getFieldOptions(field) as Record<string, string>)?.[value] || value}
                            <button
                              type="button"
                              onClick={() => {
                                fieldApi.handleChange(selectedValues.filter((v) => v !== value))
                              }}
                              className="ml-1 hover:text-destructive"
                              disabled={field.readonly}
                            >
                              ×
                            </button>
                          </Badge>
                        ))}
                      </div>
                    )}
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )
              }

              case 'checkbox':
                return (
                  <div className="flex items-center space-x-2">
                    <Checkbox
                      id={field.key}
                      name={fieldApi.name}
                      checked={Boolean(fieldValue)}
                      onCheckedChange={(checked) => fieldApi.handleChange(checked)}
                      disabled={field.readonly}
                    />
                    <Label
                      htmlFor={field.key}
                      className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                    >
                      {field.label}
                      {field.tag && (
                        <Badge variant="outline" className="ml-2">
                          {field.tag}
                        </Badge>
                      )}
                    </Label>
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )

              case 'color':
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <div className="flex items-center space-x-2">
                      <Input
                        id={field.key}
                        name={fieldApi.name}
                        type="color"
                        value={String(fieldValue || '#000000')}
                        onBlur={fieldApi.handleBlur}
                        onChange={(e) => fieldApi.handleChange(e.target.value)}
                        disabled={field.readonly}
                        className="w-16 h-10 p-1"
                      />
                      <Input
                        value={String(fieldValue || '')}
                        onBlur={fieldApi.handleBlur}
                        onChange={(e) => fieldApi.handleChange(e.target.value)}
                        disabled={field.readonly}
                        placeholder="#000000"
                      />
                    </div>
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )

              default:
                return (
                  <div className="space-y-2">
                    <Label htmlFor={field.key}>{field.label}</Label>
                    <Input
                      id={field.key}
                      name={fieldApi.name}
                      value={String(fieldValue || '')}
                      onBlur={fieldApi.handleBlur}
                      onChange={(e) => fieldApi.handleChange(e.target.value)}
                      disabled={field.readonly}
                    />
                    {field.description && <p className="text-sm text-muted-foreground">{field.description}</p>}
                    {fieldState.errors.length > 0 && (
                      <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{fieldState.errors.join(', ')}</AlertDescription>
                      </Alert>
                    )}
                  </div>
                )
            }
          }

          return (
            <div className={isSubField ? 'ml-6 space-y-4' : 'space-y-2'}>
              <div className="flex items-start gap-2">
                <div className="flex-1">{renderFieldContent()}</div>
                {/* Gear icon for fields with sub-fields */}
                {getFieldSubFields(field).length > 0 && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => handleOpenSubFields(field)}
                    className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
                  >
                    <Settings className="h-4 w-4" />
                  </Button>
                )}
              </div>
            </div>
          )
        }}
      />
    )
  }

  if (!groupSchema) {
    return (
      <div className="container mx-auto py-8">
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>No schema data available.</AlertDescription>
        </Alert>
      </div>
    )
  }

  return (
    <div className="container mx-auto py-8 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{groupSchema.label}</h1>
          <p className="text-muted-foreground">Configure your OTP authentication channels</p>
        </div>
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault()
          e.stopPropagation()
          form.handleSubmit()
        }}
        className="space-y-6"
      >
        {groupSchema.sections?.map((section, sectionIndex) => (
          <Card key={section.id || sectionIndex}>
            <CardHeader>
              <CardTitle>{section.title}</CardTitle>
              {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
            </CardHeader>
            <CardContent className="space-y-6">
              {section.fields?.map((field) => {
                // Check if field should be shown based on showIf/hideIf conditions
                const shouldShow = Object.entries(field.showIf ?? {}).every(([key, expectedValue]) => {
                  return form.getFieldValue(key) === expectedValue
                })

                const shouldHide = Object.entries(field.hideIf ?? {}).some(([key, expectedValue]) => {
                  return form.getFieldValue(key) === expectedValue
                })

                if (!shouldShow || shouldHide || field.hidden) {
                  return null
                }

                return renderField(field)
              })}
            </CardContent>
          </Card>
        ))}

        <div className="flex justify-end space-x-4">
          <Button type="button" variant="outline" onClick={() => form.reset()}>
            Reset
          </Button>
          <Button type="submit" disabled={form.state.isSubmitting}>
            {form.state.isSubmitting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Saving...
              </>
            ) : (
              <>
                <Save className="mr-2 h-4 w-4" />
                Save Changes
              </>
            )}
          </Button>
        </div>
      </form>

      {/* Sub-fields Drawer */}
      <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} direction="right">
        <DrawerContent className="h-full w-96 mr-0 rounded-none">
          <DrawerHeader className="flex flex-row items-center justify-between">
            <DrawerTitle>{selectedField ? `${selectedField.label} Settings` : 'Field Settings'}</DrawerTitle>
            <Button variant="ghost" size="sm" onClick={() => setDrawerOpen(false)} className="h-8 w-8 p-0">
              <X className="h-4 w-4" />
            </Button>
          </DrawerHeader>
          <div className="flex-1 overflow-y-auto p-6">
            {selectedField && getFieldSubFields(selectedField).length > 0 ? (
              <div className="space-y-6">
                <p className="text-sm text-muted-foreground">Configure advanced settings for {selectedField.label}</p>
                {getFieldSubFields(selectedField).map((subField) => renderField(subField, false))}
              </div>
            ) : (
              <div className="flex items-center justify-center h-32">
                <p className="text-muted-foreground">No sub-fields available</p>
              </div>
            )}
          </div>
        </DrawerContent>
      </Drawer>
    </div>
  )
}
