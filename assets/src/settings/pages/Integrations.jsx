import React, { useState, useEffect, useRef } from 'react'
import { Puzzle, FileText, CheckCircle, AlertCircle, ExternalLink, XCircle, ChevronDown, ChevronUp } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { useSetting } from '@/context/SettingsContext'
import { useAddonSettings, useAddonFieldsForSection } from '@/hooks/useAddonSettings'
import { AddonSection, AddonFieldsInjection } from '@/components/ui/AddonSection'
import { DynamicField } from '@/components/ui/DynamicField'
import { getWpSettings, __ } from '@/lib/utils'

// Get plugin status from localized data
const getPluginStatus = (pluginKey) => {
  const wpSettings = getWpSettings()
  return wpSettings.thirdPartyPlugins?.[pluginKey] || { status: 'unknown', actionUrl: '' }
}

// Status badge component
const PluginStatusBadge = ({ status }) => {
  const variants = {
    active: { label: __('Active'), className: 'wsms-bg-green-100 wsms-text-green-800 wsms-border-green-200' },
    inactive: { label: __('Inactive'), className: 'wsms-bg-yellow-100 wsms-text-yellow-800 wsms-border-yellow-200' },
    not_installed: { label: __('Not Installed'), className: 'wsms-bg-red-100 wsms-text-red-800 wsms-border-red-200' },
    unknown: { label: __('Unknown'), className: 'wsms-bg-gray-100 wsms-text-gray-800 wsms-border-gray-200' },
  }

  const variant = variants[status] || variants.unknown

  return (
    <Badge variant="outline" className={`wsms-text-xs wsms-font-medium ${variant.className}`}>
      {variant.label}
    </Badge>
  )
}

// Action link component
const PluginActionLink = ({ status, actionUrl, isExternal, pluginName }) => {
  if (status === 'active' || !actionUrl) return null

  const label = status === 'inactive' ? __('Activate') : __('Install')
  const fullLabel = pluginName ? `${label} ${pluginName}` : label

  return (
    <Button
      variant="link"
      size="sm"
      className="wsms-h-auto wsms-p-0 wsms-text-xs"
      asChild
    >
      <a
        href={actionUrl}
        target={isExternal ? '_blank' : '_self'}
        rel={isExternal ? 'noopener noreferrer' : undefined}
      >
        {fullLabel}
        {isExternal && <ExternalLink className="wsms-ml-1 wsms-h-3 wsms-w-3" />}
      </a>
    </Button>
  )
}

// Get Forminator forms data
const getForminatorFormsData = () => {
  const wpSettings = getWpSettings()
  return wpSettings.forminatorForms || { isActive: false, forms: [] }
}

// Clickable variable chip component
const VariableChip = ({ variable, onClick }) => (
  <code
    role="button"
    tabIndex={0}
    onClick={() => onClick(variable.key)}
    onKeyDown={(e) => e.key === 'Enter' && onClick(variable.key)}
    className="wsms-inline-block wsms-px-2 wsms-py-0.5 wsms-bg-muted wsms-rounded-full wsms-cursor-pointer hover:wsms-bg-muted/70 wsms-transition-colors"
    title={__('Click to insert')}
  >
    {variable.key}
  </code>
)

// Forminator form settings component
const ForminatorFormSettings = ({ form }) => {
  const [isExpanded, setIsExpanded] = useState(false)
  const messageFormRef = useRef(null)
  const messageFieldRef = useRef(null)

  // Settings for sending SMS to a specific number
  const [enableForm, setEnableForm] = useSetting(`forminator_notify_enable_form_${form.id}`, '')
  const [receiverForm, setReceiverForm] = useSetting(`forminator_notify_receiver_form_${form.id}`, '')
  const [messageForm, setMessageForm] = useSetting(`forminator_notify_message_form_${form.id}`, '')

  // Settings for sending SMS to a form field
  const [enableField, setEnableField] = useSetting(`forminator_notify_enable_field_form_${form.id}`, '')
  const [receiverField, setReceiverField] = useSetting(`forminator_notify_receiver_field_form_${form.id}`, '')
  const [messageField, setMessageField] = useSetting(`forminator_notify_message_field_form_${form.id}`, '')

  const hasFields = form.fields && Object.keys(form.fields).length > 0
  const hasAnyEnabled = enableForm === '1' || enableField === '1'

  // Insert variable at cursor position or append to end
  const insertVariable = (variable, textareaRef, currentValue, setValue) => {
    const textarea = textareaRef.current
    if (textarea) {
      const start = textarea.selectionStart
      const end = textarea.selectionEnd
      const newValue = currentValue.substring(0, start) + variable + currentValue.substring(end)
      setValue(newValue)
      // Set cursor position after inserted text
      setTimeout(() => {
        textarea.focus()
        textarea.setSelectionRange(start + variable.length, start + variable.length)
      }, 0)
    } else {
      setValue(currentValue + variable)
    }
  }

  return (
    <div className="wsms-rounded-lg wsms-border wsms-overflow-hidden">
      {/* Form Header - Clickable */}
      <button
        type="button"
        onClick={() => setIsExpanded(!isExpanded)}
        className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-p-4 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-left"
      >
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          <FileText className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
          <span className="wsms-font-medium">{form.name}</span>
          {hasAnyEnabled && (
            <Badge variant="outline" className="wsms-bg-green-100 wsms-text-green-800 wsms-border-green-200 wsms-text-xs">
              {__('Active')}
            </Badge>
          )}
        </div>
        {isExpanded ? (
          <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
        ) : (
          <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
        )}
      </button>

      {/* Form Settings - Expandable */}
      {isExpanded && (
        <div className="wsms-p-4 wsms-space-y-6 wsms-border-t">
          {/* Send SMS to a number section */}
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label className="wsms-font-medium">{__('Notify admin')}</Label>
                <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-1">
                  {__('Send an SMS to your team when someone submits this form')}
                </p>
              </div>
              <Switch
                checked={enableForm === '1'}
                onCheckedChange={(checked) => setEnableForm(checked ? '1' : '')}
              />
            </div>

            {enableForm === '1' && (
              <div className="wsms-space-y-4 wsms-pl-4 wsms-border-l-2 wsms-border-primary/20">
                <div className="wsms-space-y-2">
                  <Label>{__('Recipient phone number(s)')}</Label>
                  <Input
                    type="text"
                    value={receiverForm}
                    onChange={(e) => setReceiverForm(e.target.value)}
                    placeholder="+1234567890, +0987654321"
                  />
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    {__('Multiple numbers can be separated with commas')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label>{__('Message')}</Label>
                  <Textarea
                    ref={messageFormRef}
                    value={messageForm}
                    onChange={(e) => setMessageForm(e.target.value)}
                    rows={4}
                    placeholder={__('New form submission received from %field-name%...')}
                  />
                  {form.variables && form.variables.length > 0 && (
                    <div className="wsms-flex wsms-flex-wrap wsms-gap-1 wsms-mt-2">
                      {form.variables.map((v, i) => (
                        <VariableChip
                          key={i}
                          variable={v}
                          onClick={(key) => insertVariable(key, messageFormRef, messageForm, setMessageForm)}
                        />
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Send SMS to form field section */}
          {hasFields && (
            <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label className="wsms-font-medium">{__('Notify form submitter')}</Label>
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-1">
                    {__('Send a confirmation SMS to the person who submitted the form')}
                  </p>
                </div>
                <Switch
                  checked={enableField === '1'}
                  onCheckedChange={(checked) => setEnableField(checked ? '1' : '')}
                />
              </div>

              {enableField === '1' && (
                <div className="wsms-space-y-4 wsms-pl-4 wsms-border-l-2 wsms-border-primary/20">
                  <div className="wsms-space-y-2">
                    <Label>{__('Phone number field')}</Label>
                    <Select value={receiverField} onValueChange={setReceiverField}>
                      <SelectTrigger>
                        <SelectValue placeholder={__('Select a field...')} />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(form.fields).map(([slug, label]) => (
                          <SelectItem key={slug} value={slug}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="wsms-text-xs wsms-text-muted-foreground">
                      {__('Choose the form field that collects the phone number')}
                    </p>
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>{__('Message')}</Label>
                    <Textarea
                      ref={messageFieldRef}
                      value={messageField}
                      onChange={(e) => setMessageField(e.target.value)}
                      rows={4}
                      placeholder={__('Thank you for your submission, %field-name%!')}
                    />
                    {form.variables && form.variables.length > 0 && (
                      <div className="wsms-flex wsms-flex-wrap wsms-gap-1 wsms-mt-2">
                        {form.variables.map((v, i) => (
                          <VariableChip
                            key={i}
                            variable={v}
                            onClick={(key) => insertVariable(key, messageFieldRef, messageField, setMessageField)}
                          />
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export default function Integrations() {
  // Contact Form 7
  const [cf7Metabox, setCf7Metabox] = useSetting('cf7_metabox', '')

  // Formidable Forms
  const [formidableMetabox, setFormidableMetabox] = useSetting('formidable_metabox', '')

  // Get Forminator data
  const forminatorData = getForminatorFormsData()
  const forminatorPluginStatus = getPluginStatus('forminator')

  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields } = useAddonSettings('integrations')

  // Get add-on fields that should be injected into built-in sections
  const cf7AddonFields = useAddonFieldsForSection('integrations', 'contact-form-7')

  const integrations = [
    {
      id: 'contact-form-7',
      pluginKey: 'contact-form-7',
      name: __('Contact Form 7'),
      description: __('Send SMS notifications when Contact Form 7 forms are submitted.'),
      helpText: __('Adds an "SMS Notification" tab to the Contact Form 7 editor.'),
      settingKey: 'cf7_metabox',
      value: cf7Metabox,
      setValue: setCf7Metabox,
    },
    {
      id: 'formidable',
      pluginKey: 'formidable',
      name: __('Formidable Forms'),
      description: __('Send SMS notifications when Formidable forms are submitted.'),
      helpText: __('Adds an "SMS Notifications" tab in the Formidable form settings.'),
      settingKey: 'formidable_metabox',
      value: formidableMetabox,
      setValue: setFormidableMetabox,
    },
  ]

  // Get plugin status for each integration
  const integrationsWithStatus = integrations.map((integration) => ({
    ...integration,
    pluginStatus: getPluginStatus(integration.pluginKey),
  }))

  // Additional integrations list
  // Categories:
  // - "Included in free version" - built into WP SMS core
  // - "Requires WP SMS Pro" - requires Pro Pack
  // - "Requires [X] add-on" - requires separate add-on purchase
  const supportedPlugins = [
    // Built into free version
    { name: __('Contact Form 7'), pluginKey: 'contact-form-7', status: __('Included in free version') },
    { name: __('Formidable Forms'), pluginKey: 'formidable', status: __('Included in free version') },
    { name: __('Forminator'), pluginKey: 'forminator', status: __('Included in free version') },

    // Requires WP SMS Pro Pack
    { name: __('Gravity Forms'), pluginKey: 'gravity-forms', status: __('Requires WP SMS Pro') },
    { name: __('Quform'), pluginKey: 'quform', status: __('Requires WP SMS Pro') },
    { name: __('WooCommerce'), pluginKey: 'woocommerce', status: __('Requires WP SMS Pro') },
    { name: __('BuddyPress'), pluginKey: 'buddypress', status: __('Requires WP SMS Pro') },
    { name: __('Easy Digital Downloads'), pluginKey: 'easy-digital-downloads', status: __('Requires WP SMS Pro') },
    { name: __('WP Job Manager'), pluginKey: 'wp-job-manager', status: __('Requires WP SMS Pro') },
    { name: __('Awesome Support'), pluginKey: 'awesome-support', status: __('Requires WP SMS Pro') },
    { name: __('Ultimate Member'), pluginKey: 'ultimate-member', status: __('Requires WP SMS Pro') },

    // Requires separate add-ons
    { name: __('Elementor Forms'), pluginKey: 'elementor-pro', status: __('Requires Elementor add-on') },
    { name: __('Fluent CRM'), pluginKey: 'fluent-crm', status: __('Requires Fluent add-on') },
    { name: __('Fluent Forms'), pluginKey: 'fluentform', status: __('Requires Fluent add-on') },
    { name: __('Fluent Support'), pluginKey: 'fluent-support', status: __('Requires Fluent add-on') },
    { name: __('Paid Memberships Pro'), pluginKey: 'paid-memberships-pro', status: __('Requires Membership add-on') },
    { name: __('Simple Membership'), pluginKey: 'simple-membership', status: __('Requires Membership add-on') },
    { name: __('BookingPress'), pluginKey: 'bookingpress', status: __('Requires Booking add-on') },
    { name: __('WooCommerce Appointments'), pluginKey: 'woocommerce-appointments', status: __('Requires Booking add-on') },
    { name: __('WooCommerce Bookings'), pluginKey: 'woocommerce-bookings', status: __('Requires Booking add-on') },
    { name: __('Booking Calendar'), pluginKey: 'booking', status: __('Requires Booking add-on') },
  ]

  // Add plugin status to supported plugins
  const supportedPluginsWithStatus = supportedPlugins.map((plugin) => ({
    ...plugin,
    pluginStatus: getPluginStatus(plugin.pluginKey),
  }))

  // Map add-on section IDs to required third-party plugins
  const sectionPluginRequirements = {
    'woocommerce': 'woocommerce',
    'woo': 'woocommerce',
    'gravity-forms': 'gravity-forms',
    'gravityforms': 'gravity-forms',
    'formidable': 'formidable',
    'forminator': 'forminator',
    'elementor': 'elementor-pro',
    'contact-form-7': 'contact-form-7',
    'cf7': 'contact-form-7',
    'buddypress': 'buddypress',
    'quform': 'quform',
    'edd': 'easy-digital-downloads',
    'easy-digital-downloads': 'easy-digital-downloads',
    'job-manager': 'wp-job-manager',
    'wp-job-manager': 'wp-job-manager',
    'awesome-support': 'awesome-support',
    'ultimate-member': 'ultimate-member',
    'fluent-crm': 'fluent-crm',
    'fluentcrm': 'fluent-crm',
    'fluent-forms': 'fluentform',
    'fluentform': 'fluentform',
    'fluent-support': 'fluent-support',
    'paid-memberships-pro': 'paid-memberships-pro',
    'pmpro': 'paid-memberships-pro',
    'simple-membership': 'simple-membership',
    'bookingpress': 'bookingpress',
    'woocommerce-appointments': 'woocommerce-appointments',
    'woocommerce-bookings': 'woocommerce-bookings',
    'booking-calendar': 'booking',
    'booking': 'booking',
  }

  // Check if a section's required plugin is active
  const isSectionPluginActive = (sectionId) => {
    const lowerSectionId = sectionId.toLowerCase()

    // Check each requirement pattern
    for (const [pattern, pluginKey] of Object.entries(sectionPluginRequirements)) {
      if (lowerSectionId.includes(pattern)) {
        const status = getPluginStatus(pluginKey)
        return status.status === 'active'
      }
    }

    // If no matching pattern, assume it's available
    return true
  }

  // Get required plugin info for a section
  const getSectionPluginInfo = (sectionId) => {
    const lowerSectionId = sectionId.toLowerCase()

    for (const [pattern, pluginKey] of Object.entries(sectionPluginRequirements)) {
      if (lowerSectionId.includes(pattern)) {
        return getPluginStatus(pluginKey)
      }
    }

    return null
  }

  return (
    <div className="wsms-space-y-6">
      {/* Active Integrations */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Puzzle className="wsms-h-5 wsms-w-5" />
            {__('Form Plugin Integration')}
          </CardTitle>
          <CardDescription>
            {__('Configure SMS notifications for form submissions')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          {integrationsWithStatus.map((integration) => {
            const { pluginStatus } = integration
            const isDisabled = pluginStatus.status !== 'active'

            return (
              <div
                key={integration.id}
                className={`wsms-rounded-lg wsms-border wsms-p-4 ${isDisabled ? 'wsms-opacity-75 wsms-bg-muted/30' : ''}`}
              >
                <div className="wsms-flex wsms-items-start wsms-justify-between">
                  <div className="wsms-flex wsms-items-start wsms-gap-3">
                    <div className={`wsms-rounded-lg wsms-p-2 ${isDisabled ? 'wsms-bg-muted' : 'wsms-bg-primary/10'}`}>
                      <FileText className={`wsms-h-5 wsms-w-5 ${isDisabled ? 'wsms-text-muted-foreground' : 'wsms-text-primary'}`} />
                    </div>
                    <div>
                      <div className="wsms-flex wsms-items-center wsms-gap-2">
                        <h3 className="wsms-font-medium">{integration.name}</h3>
                        <PluginStatusBadge status={pluginStatus.status} />
                      </div>
                      <p className="wsms-mt-1 wsms-text-sm wsms-text-muted-foreground">
                        {integration.description}
                      </p>
                      {!isDisabled && integration.helpText && (
                        <p className="wsms-mt-2 wsms-text-xs wsms-text-muted-foreground">
                          {integration.helpText}
                        </p>
                      )}
                    </div>
                  </div>
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    {isDisabled && (
                      <PluginActionLink
                        status={pluginStatus.status}
                        actionUrl={pluginStatus.actionUrl}
                        isExternal={pluginStatus.isExternal}
                        pluginName={integration.name}
                      />
                    )}
                    <Switch
                      checked={integration.value === '1'}
                      onCheckedChange={(checked) => integration.setValue(checked ? '1' : '')}
                      disabled={isDisabled}
                      aria-label={__('Enable') + ' ' + integration.name}
                    />
                  </div>
                </div>
                {/* Inject add-on fields for this integration */}
                {integration.id === 'contact-form-7' && cf7AddonFields.length > 0 && !isDisabled && (
                  <AddonFieldsInjection fields={cf7AddonFields} />
                )}
              </div>
            )
          })}

          {/* Forminator Integration */}
          <div className={`wsms-rounded-lg wsms-border wsms-p-4 ${forminatorPluginStatus.status !== 'active' ? 'wsms-opacity-75 wsms-bg-muted/30' : ''}`}>
            <div className="wsms-flex wsms-items-start wsms-justify-between">
              <div className="wsms-flex wsms-items-start wsms-gap-3">
                <div className={`wsms-rounded-lg wsms-p-2 ${forminatorPluginStatus.status !== 'active' ? 'wsms-bg-muted' : 'wsms-bg-primary/10'}`}>
                  <FileText className={`wsms-h-5 wsms-w-5 ${forminatorPluginStatus.status !== 'active' ? 'wsms-text-muted-foreground' : 'wsms-text-primary'}`} />
                </div>
                <div>
                  <div className="wsms-flex wsms-items-center wsms-gap-2">
                    <h3 className="wsms-font-medium">{__('Forminator')}</h3>
                    <PluginStatusBadge status={forminatorPluginStatus.status} />
                  </div>
                  <p className="wsms-mt-1 wsms-text-sm wsms-text-muted-foreground">
                    {__('Send SMS notifications when Forminator forms are submitted.')}
                  </p>
                </div>
              </div>
              {forminatorPluginStatus.status !== 'active' && (
                <PluginActionLink
                  status={forminatorPluginStatus.status}
                  actionUrl={forminatorPluginStatus.actionUrl}
                  isExternal={forminatorPluginStatus.isExternal}
                  pluginName="Forminator"
                />
              )}
            </div>

            {/* Forminator per-form settings */}
            {forminatorPluginStatus.status === 'active' && forminatorData.isActive && (
              <div className="wsms-mt-4">
                {forminatorData.forms.length > 0 ? (
                  <div className="wsms-space-y-3">
                    {forminatorData.forms.map((form) => (
                      <ForminatorFormSettings key={form.id} form={form} />
                    ))}
                  </div>
                ) : (
                  <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
                    <p className="wsms-text-sm wsms-text-muted-foreground">
                      {__('No forms found. Please create a form in Forminator first.')}
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Add-on Defined Sections */}
      {addonSections.map((section) => {
        const isPluginActive = isSectionPluginActive(section.id)
        const pluginInfo = getSectionPluginInfo(section.id)

        if (!isPluginActive && pluginInfo) {
          // Show disabled version with install/activate prompt
          return (
            <Card key={section.id} className="wsms-opacity-75">
              <CardHeader>
                <div className="wsms-flex wsms-items-center wsms-justify-between">
                  <div>
                    <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                      {section.title}
                      <PluginStatusBadge status={pluginInfo.status} />
                    </CardTitle>
                    {section.description && (
                      <CardDescription>{section.description}</CardDescription>
                    )}
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
                  <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-2">
                    {pluginInfo.name} {__('is required to use these settings.')}
                  </p>
                  <PluginActionLink
                    status={pluginInfo.status}
                    actionUrl={pluginInfo.actionUrl}
                    isExternal={pluginInfo.isExternal}
                    pluginName={pluginInfo.name}
                  />
                </div>
              </CardContent>
            </Card>
          )
        }

        return (
          <AddonSection
            key={section.id}
            section={section}
            fields={fieldsBySection[section.id] || []}
          />
        )
      })}

      {/* Standalone Add-on Fields (fields without a section) */}
      {standaloneFields.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>{__('Additional Add-on Settings')}</CardTitle>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            {standaloneFields.map((field) => (
              <DynamicField key={field.id} field={field} />
            ))}
          </CardContent>
        </Card>
      )}

      {/* Other Supported Plugins */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Additional Integrations')}</CardTitle>
          <CardDescription>
            {__('Other plugins supported through WP SMS and its add-ons')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-space-y-3">
            {supportedPluginsWithStatus.map((plugin) => {
              const { pluginStatus } = plugin
              const isAvailable = pluginStatus.status === 'active'

              return (
                <div
                  key={plugin.name}
                  className={`wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-3 ${!isAvailable ? 'wsms-bg-muted/30' : ''}`}
                >
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    <FileText className={`wsms-h-4 wsms-w-4 ${isAvailable ? 'wsms-text-primary' : 'wsms-text-muted-foreground'}`} />
                    <div>
                      <span className="wsms-font-medium">{plugin.name}</span>
                      {plugin.note && (
                        <span className="wsms-block wsms-text-xs wsms-text-muted-foreground">{plugin.note}</span>
                      )}
                    </div>
                    <PluginStatusBadge status={pluginStatus.status} />
                  </div>
                  <div className="wsms-flex wsms-items-center wsms-gap-4">
                    {!isAvailable && pluginStatus.actionUrl && (
                      <PluginActionLink
                        status={pluginStatus.status}
                        actionUrl={pluginStatus.actionUrl}
                        isExternal={pluginStatus.isExternal}
                        pluginName=""
                      />
                    )}
                    <span className="wsms-text-sm wsms-text-muted-foreground">{plugin.status}</span>
                  </div>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
