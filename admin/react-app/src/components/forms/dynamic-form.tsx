"use client"

import React from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Checkbox } from "@/components/ui/checkbox"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Button } from "@/components/ui/button"
import { Loader2, AlertCircle } from "lucide-react"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Separator } from "@/components/ui/separator"

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
}

export function DynamicForm({ schema, savedValues, loading, error }: DynamicFormProps) {
  const [formData, setFormData] = React.useState<Record<string, any>>({})

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
              <p className="text-sm text-muted-foreground">{description}</p>
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
              placeholder={description}
            />
            {description && (
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
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
              placeholder={description}
            />
            {description && (
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
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
              <p className="text-sm text-muted-foreground ml-6">{description}</p>
            )}
          </div>
        )

      case 'select':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Select
              value={formData[key] || ''}
              onValueChange={(value) => handleFieldChange(key, value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select an option" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(options).map(([value, label]) => (
                  <SelectItem key={value} value={value}>
                    {typeof label === 'string' ? label : value}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {description && (
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
          </div>
        )

      case 'advancedselect':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Select
              value={formData[key] || ''}
              onValueChange={(value) => handleFieldChange(key, value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select an option" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(options).map(([groupKey, groupOptions]) => {
                  if (typeof groupOptions === 'object') {
                    return (
                      <div key={groupKey}>
                        <div className="px-2 py-1.5 text-sm font-semibold text-muted-foreground">
                          {groupKey}
                        </div>
                        {Object.entries(groupOptions).map(([value, label]) => (
                          <SelectItem key={value} value={value}>
                            {typeof label === 'string' ? label : value}
                          </SelectItem>
                        ))}
                      </div>
                    )
                  }
                  return (
                    <SelectItem key={groupKey} value={groupKey}>
                      {typeof groupOptions === 'string' ? groupOptions : groupKey}
                    </SelectItem>
                  )
                })}
              </SelectContent>
            </Select>
            {description && (
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
          </div>
        )

      case 'countryselect':
        return (
          <div key={key} className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Select
              value={formData[key] || ''}
              onValueChange={(value) => handleFieldChange(key, value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select a country" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(options).map(([code, name]) => (
                  <SelectItem key={code} value={code}>
                    {typeof name === 'string' ? name : code}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {description && (
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
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
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
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
        <form className="space-y-6">
          {schema.fields
            .sort((a, b) => a.order - b.order)
            .map(renderField)}
          
          <div className="flex justify-end space-x-2 pt-6">
            <Button type="button" variant="outline">
              Reset
            </Button>
            <Button type="submit">
              Save Changes
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
} 