import React from 'react'
import { useGatewayFields } from '@/hooks/use-gateway-fields'
import { TextField, PasswordField } from './settings-fields'
import { SelectField } from './settings-fields'
import { TextareaField } from './settings-fields'
import { FieldWrapper } from './field-wrapper'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Loader2 } from 'lucide-react'

interface GatewayFieldsProps {
  gatewayName: string | null
  savedValues: { [key: string]: any }
  onFieldChange: (key: string, value: any) => void
}

export function GatewayFields({ gatewayName, savedValues, onFieldChange }: GatewayFieldsProps) {
  const { data: gatewayFields, loading, error } = useGatewayFields(gatewayName)

  if (loading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center py-8">
          <Loader2 className="h-6 w-6 animate-spin" />
          <span className="ml-2">Loading gateway fields...</span>
        </CardContent>
      </Card>
    )
  }

  if (error) {
    return (
      <Card>
        <CardContent className="py-4">
          <Alert variant="destructive">
            <AlertDescription>
              Failed to load gateway fields: {error}
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    )
  }

  if (!gatewayFields || gatewayFields.length === 0) {
    return null
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Gateway Configuration</CardTitle>
        <CardDescription>
          Configure the specific settings for your selected gateway.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {gatewayFields.map((field) => {
          const value = savedValues[field.id] || ''
          
          switch (field.type) {
            case 'select':
              return (
                <SelectField
                  key={field.id}
                  label={field.name}
                  value={value}
                  description={field.desc}
                  options={field.options ? Object.entries(field.options).map(([key, val]) => ({
                    value: key,
                    label: val as string
                  })) : []}
                  onChange={(newValue) => onFieldChange(field.id, newValue)}
                />
              )
            
            case 'textarea':
              return (
                <TextareaField
                  key={field.id}
                  label={field.name}
                  value={value}
                  description={field.desc}
                  onChange={(newValue) => onFieldChange(field.id, newValue)}
                />
              )
            
            case 'password':
              return (
                <PasswordField
                  key={field.id}
                  label={field.name}
                  value={value}
                  description={field.desc}
                  onChange={(newValue) => onFieldChange(field.id, newValue)}
                />
              )
            
            default: // text, etc.
              return (
                <TextField
                  key={field.id}
                  label={field.name}
                  value={value}
                  description={field.desc}
                  onChange={(newValue) => onFieldChange(field.id, newValue)}
                />
              )
          }
        })}
      </CardContent>
    </Card>
  )
}