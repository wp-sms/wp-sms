import React, { useState, useMemo, useRef } from 'react'
import { Search, CheckCircle, Radio, Send, Loader2, Shield, Zap, BookOpen, ExternalLink, XCircle, RotateCcw, Code, Unplug } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { InputField, SelectField, SwitchField, MultiSelectField } from '@/components/ui/form-field'
import { Tip, CollapsibleSection, HelpLink, SectionDivider } from '@/components/ui/ux-helpers'
import { useSettings, useSetting } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { getWpSettings, cn, getGatewayDisplayName, __ } from '@/lib/utils'

export default function Gateway() {
  const { testGatewayConnection, getSetting, updateSetting } = useSettings()
  const { toast } = useToast()
  const { gateways = {}, countries = {}, gateway: gatewayCapabilities = {} } = getWpSettings()

  // Get dynamic gateway fields and help from capabilities
  const gatewayFields = gatewayCapabilities.gatewayFields || {}
  const gatewayHelp = gatewayCapabilities.help || ''
  const gatewayDocumentUrl = gatewayCapabilities.documentUrl || ''

  const [gatewayName, setGatewayName] = useSetting('gateway_name', '')

  // Track the saved gateway (the one capabilities/fields are loaded for)
  // This is set on initial load and represents what's actually saved in the database
  const savedGatewayRef = useRef(gatewayName)

  // Detect if user has selected a different gateway but hasn't saved yet
  // When true, the capabilities/credentials/guide shown are for the OLD gateway
  const hasUnsavedGatewayChange = gatewayName && gatewayName !== savedGatewayRef.current

  const [deliveryMethod, setDeliveryMethod] = useSetting('sms_delivery_method', 'api_direct_send')
  const [sendUnicode, setSendUnicode] = useSetting('send_unicode', '')
  const [cleanNumbers, setCleanNumbers] = useSetting('clean_numbers', '')
  const [localNumbersOnly, setLocalNumbersOnly] = useSetting('send_only_local_numbers', '')
  const [localNumbersCountries, setLocalNumbersCountries] = useSetting('only_local_numbers_countries', [])

  const [creditInMenu, setCreditInMenu] = useSetting('account_credit_in_menu', '')
  const [creditInSendSms, setCreditInSendSms] = useSetting('account_credit_in_sendsms', '')

  const [searchQuery, setSearchQuery] = useState('')
  const [testing, setTesting] = useState(false)
  const [connectionTested, setConnectionTested] = useState(false)
  const [connectionSuccess, setConnectionSuccess] = useState(false)
  const [rawResponse, setRawResponse] = useState(null)
  const [showDisconnectConfirm, setShowDisconnectConfirm] = useState(false)
  const [pendingGateway, setPendingGateway] = useState(null)

  const gatewayList = useMemo(() => {
    const list = []
    if (gateways && typeof gateways === 'object') {
      Object.entries(gateways).forEach(([region, providers]) => {
        if (typeof providers === 'object') {
          Object.entries(providers).forEach(([key, name]) => {
            list.push({ key, name, region })
          })
        }
      })
    }
    return list
  }, [gateways])

  const filteredGateways = useMemo(() => {
    // Filter out empty/placeholder gateways first
    let list = gatewayList.filter(
      (g) => g.key && g.key.trim() !== '' && !g.name.toLowerCase().includes('please select')
    )

    if (!searchQuery) return list
    const query = searchQuery.toLowerCase()
    return list.filter(
      (g) => g.name.toLowerCase().includes(query) || g.region.toLowerCase().includes(query)
    )
  }, [gatewayList, searchQuery])

  const groupedGateways = useMemo(() => {
    return filteredGateways.reduce((acc, gateway) => {
      if (!acc[gateway.region]) acc[gateway.region] = []
      acc[gateway.region].push(gateway)
      return acc
    }, {})
  }, [filteredGateways])

  const handleTestConnection = async () => {
    setTesting(true)
    try {
      const result = await testGatewayConnection()
      setConnectionTested(true)
      setConnectionSuccess(result.success)
      setRawResponse(result.rawResponse || null)
      toast({
        title: result.success ? 'Connection Successful' : 'Connection Failed',
        description: result.success ? `Credit: ${result.credit}` : result.error,
        variant: result.success ? 'success' : 'destructive',
      })
    } catch (error) {
      setConnectionTested(true)
      setConnectionSuccess(false)
      toast({ title: 'Error', description: error.message, variant: 'destructive' })
    }
    setTesting(false)
  }

  return (
    <div className="wsms-space-y-4 wsms-stagger-children">
      {/* Helpful tip for new users */}
      {!gatewayName && (
        <Tip>
          <strong>{__('Need help choosing a gateway?')}</strong> {__('Consider factors like coverage area, pricing, and API features. Most gateways offer free trial credits to test before committing.')}
        </Tip>
      )}

      {/* Gateway Selection */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('SMS Gateway')}
          </CardTitle>
          <CardDescription>{__('Select your SMS service provider. Configure credentials below after selecting.')}</CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-relative">
            <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms-h-4 wsms-w-4 wsms--translate-y-1/2 wsms-text-muted-foreground" />
            <Input
              placeholder={__('Search gateways...')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="wsms-pl-9"
            />
          </div>

          <div className="wsms-max-h-[280px] wsms-overflow-y-auto wsms-rounded-md wsms-border wsms-border-border wsms-p-4 wsms-scrollbar-thin wsms-bg-muted/30">
            {Object.entries(groupedGateways).map(([region, providers]) => (
              <div key={region} className="wsms-mb-4 last:wsms-mb-0">
                <p className="wsms-mb-2 wsms-text-[11px] wsms-font-semibold wsms-uppercase wsms-text-muted-foreground wsms-tracking-wide">
                  {region}
                </p>
                <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                  {providers.map((gateway) => (
                    <button
                      key={gateway.key}
                      onClick={() => {
                        // If same gateway, do nothing
                        if (gateway.key === gatewayName) return
                        // If no gateway selected, select directly
                        if (!gatewayName) {
                          setGatewayName(gateway.key)
                          return
                        }
                        // If different gateway, show confirmation
                        setPendingGateway(gateway.key)
                        setShowDisconnectConfirm(false) // Clear any disconnect confirmation
                      }}
                      className={cn(
                        'wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-px-3 wsms-py-2 wsms-text-left wsms-text-[12px] wsms-transition-colors',
                        gatewayName === gateway.key
                          ? 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-font-medium'
                          : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
                      )}
                    >
                      {gatewayName === gateway.key && (
                        <CheckCircle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
                      )}
                      <span className="wsms-truncate">{gateway.name}</span>
                    </button>
                  ))}
                </div>
              </div>
            ))}
            {Object.keys(groupedGateways).length === 0 && (
              <div className="wsms-py-8 wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
                {__('No gateways found')}
              </div>
            )}
          </div>

          {/* Selection Status Bar */}
          <div className={cn(
            "wsms-rounded-lg wsms-border wsms-transition-all wsms-duration-200",
            gatewayName
              ? "wsms-border-primary/30 wsms-bg-primary/5"
              : "wsms-border-dashed wsms-border-border wsms-bg-muted/30"
          )}>
            {gatewayName ? (
              <>
                {/* Selected State */}
                <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-3 wsms-p-3">
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md wsms-bg-primary/10">
                      <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                    </div>
                    <div>
                      <p className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                        {__('Selected Gateway')}
                      </p>
                      <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">
                        {getGatewayDisplayName(gatewayName, gateways)}
                      </p>
                    </div>
                  </div>
                  {pendingGateway ? (
                    // Switch gateway confirmation
                    <div className="wsms-flex wsms-items-center wsms-gap-2">
                      <span className="wsms-text-[12px] wsms-text-muted-foreground">
                        {__('Switch to')} <strong className="wsms-text-foreground">{getGatewayDisplayName(pendingGateway, gateways)}</strong>?
                      </span>
                      <button
                        onClick={() => {
                          setGatewayName(pendingGateway)
                          setPendingGateway(null)
                        }}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-bg-primary wsms-text-primary-foreground wsms-transition-colors hover:wsms-bg-primary/90"
                      >
                        {__('Switch')}
                      </button>
                      <button
                        onClick={() => setPendingGateway(null)}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-border wsms-border-border wsms-bg-background wsms-transition-colors hover:wsms-bg-accent"
                      >
                        {__('Cancel')}
                      </button>
                    </div>
                  ) : showDisconnectConfirm ? (
                    // Disconnect confirmation
                    <div className="wsms-flex wsms-items-center wsms-gap-2">
                      <span className="wsms-text-[12px] wsms-text-muted-foreground">{__('Are you sure?')}</span>
                      <button
                        onClick={() => {
                          setGatewayName('')
                          setShowDisconnectConfirm(false)
                        }}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-bg-destructive wsms-text-destructive-foreground wsms-transition-colors hover:wsms-bg-destructive/90"
                      >
                        {__('Disconnect')}
                      </button>
                      <button
                        onClick={() => setShowDisconnectConfirm(false)}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-border wsms-border-border wsms-bg-background wsms-transition-colors hover:wsms-bg-accent"
                      >
                        {__('Cancel')}
                      </button>
                    </div>
                  ) : (
                    // Normal state - show disconnect button
                    <button
                      onClick={() => setShowDisconnectConfirm(true)}
                      className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-rounded-md wsms-px-3 wsms-py-1.5 wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground wsms-transition-colors hover:wsms-bg-destructive/10 hover:wsms-text-destructive"
                    >
                      <Unplug className="wsms-h-3.5 wsms-w-3.5" />
                      {__('Disconnect')}
                    </button>
                  )}
                </div>
                {/* Inline Capabilities */}
                <div className="wsms-border-t wsms-border-primary/20 wsms-px-3 wsms-py-2 wsms-bg-primary/[0.02]">
                  {hasUnsavedGatewayChange ? (
                    // Show save prompt when gateway changed but not saved
                    <p className="wsms-text-[11px] wsms-text-amber-600 dark:wsms-text-amber-400">
                      {__('Save your changes to see capabilities and configure credentials for this gateway.')}
                    </p>
                  ) : (
                    // Show actual capabilities for saved gateway
                    <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-flex-wrap">
                      <span className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                        {__('Capabilities:')}
                      </span>
                      <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-flex-wrap">
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.flash === 'enable' ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.flash === 'enable' ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('Flash SMS')}
                        </span>
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.bulk_send !== false ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.bulk_send !== false ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('Bulk Send')}
                        </span>
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.supportMedia === true ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.supportMedia === true ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('MMS')}
                        </span>
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.supportIncoming === true ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.supportIncoming === true ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('Incoming SMS')}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
              </>
            ) : (
              <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3">
                {/* Empty State */}
                <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md wsms-bg-muted">
                  <Radio className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                </div>
                <div>
                  <p className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                    {__('No Gateway Selected')}
                  </p>
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Choose a provider from the list above')}
                  </p>
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Gateway Guide - only show for saved gateway */}
      {gatewayName && !hasUnsavedGatewayChange && (gatewayHelp || gatewayDocumentUrl) && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <BookOpen className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Gateway Guide')}
            </CardTitle>
            <CardDescription>{__('Setup instructions for')} {getGatewayDisplayName(gatewayName, gateways)}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-space-y-3">
              {gatewayHelp && (
                <div
                  className="wsms-text-[13px] wsms-text-foreground [&_a]:wsms-text-primary [&_a]:wsms-underline [&_a]:hover:wsms-text-primary/80"
                  dangerouslySetInnerHTML={{ __html: gatewayHelp }}
                />
              )}
              {gatewayDocumentUrl && (
                <a
                  href={gatewayDocumentUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[13px] wsms-text-primary hover:wsms-text-primary/80 wsms-font-medium"
                >
                  <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
                  {__('View Full Documentation')}
                </a>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Gateway Credentials - only show for saved gateway */}
      {gatewayName && !hasUnsavedGatewayChange && Object.keys(gatewayFields).length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Credentials')}
            </CardTitle>
            <CardDescription>{__('API credentials for')} {getGatewayDisplayName(gatewayName, gateways)}</CardDescription>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            <div className="wsms-grid wsms-grid-cols-1 wsms-gap-4 md:wsms-grid-cols-2">
              {Object.entries(gatewayFields).map(([key, field]) => {
                const fieldValue = getSetting(field.id, '')
                const isPassword = key === 'password' || field.id.includes('password')

                // Handle select fields
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
                      onValueChange={(value) => updateSetting(field.id, value)}
                      placeholder={field.placeholder || `Select ${field.name}`}
                      options={options}
                    />
                  )
                }

                // Handle text/password fields
                return (
                  <InputField
                    key={field.id}
                    label={field.name}
                    description={field.desc}
                    type={isPassword ? 'password' : 'text'}
                    value={fieldValue}
                    onChange={(e) => updateSetting(field.id, e.target.value)}
                    placeholder={field.placeholder || ''}
                  />
                )
              })}
            </div>

            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-pt-4 wsms-border-t wsms-border-border">
              <div className="wsms-flex wsms-items-center wsms-gap-3">
                <Button size="sm" onClick={handleTestConnection} disabled={testing}>
                  {testing ? (
                    <>
                      <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1 wsms-animate-spin" />
                      {__('Testing...')}
                    </>
                  ) : (
                    <>
                      <Send className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                      {__('Test Connection')}
                    </>
                  )}
                </Button>
                <span className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Verify your credentials are working')}
                </span>
              </div>
            </div>

            {/* Raw API Response */}
            {connectionTested && rawResponse && (
              <CollapsibleSection
                title={__('API Response')}
                description={__('Raw response from the gateway for debugging')}
                defaultOpen={false}
              >
                <div className="wsms-rounded-lg wsms-bg-muted wsms-p-3 wsms-font-mono wsms-text-[12px] wsms-overflow-x-auto">
                  <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mb-2 wsms-text-muted-foreground">
                    <Code className="wsms-h-3.5 wsms-w-3.5" />
                    <span>{__('Gateway Response:')}</span>
                  </div>
                  <pre className="wsms-whitespace-pre-wrap wsms-break-all wsms-text-foreground">
                    {rawResponse}
                  </pre>
                </div>
              </CollapsibleSection>
            )}
          </CardContent>
        </Card>
      )}

      {/* Delivery Settings */}
      <Card className="wsms-relative wsms-z-10">
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Zap className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Delivery Settings')}
          </CardTitle>
          <CardDescription>
            {__('Configure how messages are processed and delivered')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SelectField
            label={__('Delivery Method')}
            description={__('How SMS messages are processed and sent.')}
            value={deliveryMethod}
            onValueChange={setDeliveryMethod}
            placeholder={__('Select method')}
            options={[
              { value: 'api_direct_send', label: __('Instant — Send immediately when triggered') },
              { value: 'api_async_send', label: __('Background — Process in background (reduces page load time)') },
              { value: 'api_queued_send', label: __('Queue — Add to queue for batch processing') },
            ]}
          />

          {deliveryMethod === 'api_queued_send' && (
            <Tip variant="info">
              {__('Queue mode requires a cron job to process messages. Configure WP-Cron or set up a real cron job for reliable delivery.')}
            </Tip>
          )}

          <SectionDivider>{__('Message Formatting')}</SectionDivider>

          <div className="wsms-divide-y wsms-divide-border wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            <SwitchField
              label={__('Enable Unicode')}
              description={__('Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS.')}
              checked={sendUnicode === '1'}
              onCheckedChange={(checked) => setSendUnicode(checked ? '1' : '')}
              className="wsms-px-4"
            />
            <SwitchField
              label={__('Auto-format Numbers')}
              description={__('Automatically remove spaces and special characters from phone numbers before sending.')}
              checked={cleanNumbers === '1'}
              onCheckedChange={(checked) => setCleanNumbers(checked ? '1' : '')}
              className="wsms-px-4"
            />
          </div>

          <CollapsibleSection
            title={__('Country Restrictions')}
            description={__('Limit SMS delivery to specific countries')}
          >
            <div className="wsms-space-y-4">
              <SwitchField
                label={__('Restrict to Specific Countries')}
                description={__('Only send SMS to phone numbers from selected countries.')}
                checked={localNumbersOnly === '1'}
                onCheckedChange={(checked) => setLocalNumbersOnly(checked ? '1' : '')}
              />

              {localNumbersOnly === '1' && (
                <MultiSelectField
                  label={__('Allowed Countries')}
                  options={countries}
                  value={localNumbersCountries}
                  onValueChange={setLocalNumbersCountries}
                  placeholder={__('Select countries...')}
                  searchPlaceholder={__('Search countries...')}
                  description={__('SMS will only be sent to numbers from these countries.')}
                />
              )}
            </div>
          </CollapsibleSection>
        </CardContent>
      </Card>

      {/* Credit Display */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Credit Display')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="wsms-divide-y wsms-divide-border wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            <SwitchField
              label={__('Show Credit in Menu')}
              description={__('Display your SMS credit balance in the WordPress admin menu bar.')}
              checked={creditInMenu === '1'}
              onCheckedChange={(checked) => setCreditInMenu(checked ? '1' : '')}
              className="wsms-px-4"
            />
            <SwitchField
              label={__('Show Credit on Send Page')}
              description={__('Display your remaining SMS credits when composing messages.')}
              checked={creditInSendSms === '1'}
              onCheckedChange={(checked) => setCreditInSendSms(checked ? '1' : '')}
              className="wsms-px-4"
            />
          </div>
        </CardContent>
      </Card>

      {/* Setup Wizard */}
      <Card>
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4">
          <div>
            <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
              {__('Setup Wizard')}
            </p>
            <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-0.5">
              {__('Re-run the guided setup to update your gateway configuration')}
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => window.wpSmsOpenWizard?.()}
          >
            <RotateCcw className="wsms-h-4 wsms-w-4 wsms-mr-1" />
            {__('Re-run Wizard')}
          </Button>
        </div>
      </Card>
    </div>
  )
}
