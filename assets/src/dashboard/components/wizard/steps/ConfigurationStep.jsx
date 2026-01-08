import React, { useState } from 'react'
import { Settings, Loader2, CheckCircle, XCircle, ExternalLink, AlertCircle } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { InputField, SelectField } from '@/components/ui/form-field'
import { Tip } from '@/components/ui/ux-helpers'
import { settingsApi } from '@/api/settingsApi'
import { getWpSettings, __ } from '@/lib/utils'

/**
 * Configuration step - Enter gateway credentials and test connection
 */
export default function ConfigurationStep({
  gatewayName,
  credentials,
  onCredentialChange,
  onTestSuccess,
}) {
  const { gateway: gatewayCapabilities = {}, gateways = {} } = getWpSettings()
  const gatewayFields = gatewayCapabilities.gatewayFields || {}
  const gatewayHelp = gatewayCapabilities.help || ''
  const gatewayDocumentUrl = gatewayCapabilities.documentUrl || ''

  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState(null)

  const getGatewayDisplayName = () => {
    if (gateways && typeof gateways === 'object') {
      for (const providers of Object.values(gateways)) {
        if (typeof providers === 'object' && providers[gatewayName]) {
          return providers[gatewayName]
        }
      }
    }
    return gatewayName
  }

  const handleTestConnection = async () => {
    setTesting(true)
    setTestResult(null)

    try {
      await settingsApi.updateSettings({ settings: credentials })
      const result = await settingsApi.testGateway()

      setTestResult({
        success: true,
        isActive: result.success,
        credit: result.credit,
      })

      if (result.success) {
        onTestSuccess?.(result)
      }
    } catch (error) {
      setTestResult({
        success: false,
        isActive: false,
        error: error.message,
      })
    }

    setTesting(false)
  }

  const handleFieldChange = (fieldId, value) => {
    onCredentialChange({
      ...credentials,
      [fieldId]: value,
    })
  }

  const hasFields = Object.keys(gatewayFields).length > 0

  return (
    <div className="wsms-max-w-xl wsms-mx-auto">
      {/* Header */}
      <div className="wsms-text-center wsms-mb-6">
        <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
          {__('Configure')} {getGatewayDisplayName()}
        </h2>
        <p className="wsms-text-[12px] wsms-text-muted-foreground">
          {__('Enter your API credentials to connect.')}
        </p>
      </div>

      {/* Help Text */}
      {gatewayHelp && (
        <div className="wsms-mb-4 wsms-rounded-md wsms-border wsms-border-border wsms-bg-muted/30 wsms-p-4">
          <div
            className="wsms-text-[12px] wsms-text-foreground [&_a]:wsms-text-primary [&_a]:wsms-underline"
            dangerouslySetInnerHTML={{ __html: gatewayHelp }}
          />
          {gatewayDocumentUrl && (
            <a
              href={gatewayDocumentUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-primary wsms-mt-2 hover:wsms-underline wsms-font-medium"
            >
              <ExternalLink className="wsms-h-3 wsms-w-3" />
              {__('View Documentation')}
            </a>
          )}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Settings className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Gateway Credentials')}
          </CardTitle>
          <CardDescription>{__('Enter the credentials from your gateway provider.')}</CardDescription>
        </CardHeader>
        <CardContent>
          {hasFields ? (
            <div className="wsms-grid wsms-grid-cols-1 wsms-gap-4 md:wsms-grid-cols-2">
              {Object.entries(gatewayFields).map(([key, field]) => {
                const fieldValue = credentials[field.id] || ''
                const isPassword = key === 'password' || field.id.includes('password') || field.id.includes('key')

                if (field.type === 'select' && field.options) {
                  const options = Object.entries(field.options).map(([value, label]) => ({
                    value,
                    label,
                  }))
                  return (
                    <SelectField
                      key={field.id}
                      label={field.name}
                      description={field.desc}
                      value={fieldValue}
                      onValueChange={(value) => handleFieldChange(field.id, value)}
                      placeholder={field.placeholder || `Select ${field.name}`}
                      options={options}
                    />
                  )
                }

                return (
                  <InputField
                    key={field.id}
                    label={field.name}
                    description={field.desc}
                    type={isPassword ? 'password' : 'text'}
                    value={fieldValue}
                    onChange={(e) => handleFieldChange(field.id, e.target.value)}
                    placeholder={field.placeholder || ''}
                  />
                )
              })}
            </div>
          ) : (
            <Tip variant="info">
              <AlertCircle className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-inline" />
              {__('Save your gateway selection first to load credential fields.')}
            </Tip>
          )}
        </CardContent>
        <CardFooter className="wsms-justify-between">
          <div>
            <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">{__('Test Connection')}</p>
            <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Verify credentials work')}</p>
          </div>
          <Button onClick={handleTestConnection} disabled={testing || !hasFields} size="sm">
            {testing ? (
              <>
                <Loader2 className="wsms-h-3.5 wsms-w-3.5 wsms-mr-1.5 wsms-animate-spin" />
                {__('Testing...')}
              </>
            ) : (
              __('Test Connection')
            )}
          </Button>
        </CardFooter>
      </Card>

      {/* Test Result */}
      {testResult && (
        <div className="wsms-mt-4">
          {testResult.isActive ? (
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3 wsms-rounded-md wsms-bg-success/5 wsms-border wsms-border-success/20">
              <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-success wsms-shrink-0" />
              <div>
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-success">{__('Connection successful!')}</p>
                {testResult.credit !== undefined && (
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">
                    {__('Balance:')} {testResult.credit}
                  </p>
                )}
              </div>
            </div>
          ) : (
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3 wsms-rounded-md wsms-bg-destructive/5 wsms-border wsms-border-destructive/20">
              <XCircle className="wsms-h-4 wsms-w-4 wsms-text-destructive wsms-shrink-0" />
              <p className="wsms-text-[12px] wsms-text-destructive">
                {testResult.error || __('Connection failed. Check your credentials.')}
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
