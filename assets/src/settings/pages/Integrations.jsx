import React, { useState, useRef } from 'react'
import { Puzzle, ChevronDown, ChevronUp, ExternalLink, ShoppingCart, Users, CreditCard, Briefcase, HeadphonesIcon, UserCircle, Mail, ClipboardList, Calendar, Layers, FileInput } from 'lucide-react'
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
    not_installed: { label: __('Not Installed'), className: 'wsms-bg-gray-100 wsms-text-gray-600 wsms-border-gray-200' },
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

// Clickable variable chip component - matches TemplateTextarea styling
const VariableChip = ({ variable, onClick }) => (
  <button
    type="button"
    onClick={() => onClick(variable.key)}
    className="wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/30 hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary wsms-transition-colors wsms-cursor-pointer focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20"
    title={__('Click to insert')}
  >
    {variable.key}
  </button>
)

// Forminator form settings component
const ForminatorFormSettings = ({ form }) => {
  const [isExpanded, setIsExpanded] = useState(false)
  const messageFormRef = useRef(null)
  const messageFieldRef = useRef(null)

  const [enableForm, setEnableForm] = useSetting(`forminator_notify_enable_form_${form.id}`, '')
  const [receiverForm, setReceiverForm] = useSetting(`forminator_notify_receiver_form_${form.id}`, '')
  const [messageForm, setMessageForm] = useSetting(`forminator_notify_message_form_${form.id}`, '')
  const [enableField, setEnableField] = useSetting(`forminator_notify_enable_field_form_${form.id}`, '')
  const [receiverField, setReceiverField] = useSetting(`forminator_notify_receiver_field_form_${form.id}`, '')
  const [messageField, setMessageField] = useSetting(`forminator_notify_message_field_form_${form.id}`, '')

  const hasFields = form.fields && Object.keys(form.fields).length > 0
  const hasAnyEnabled = enableForm === '1' || enableField === '1'

  const insertVariable = (variable, textareaRef, currentValue, setValue) => {
    const textarea = textareaRef.current
    if (textarea) {
      const start = textarea.selectionStart
      const end = textarea.selectionEnd
      const newValue = currentValue.substring(0, start) + variable + currentValue.substring(end)
      setValue(newValue)
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
      <button
        type="button"
        onClick={() => setIsExpanded(!isExpanded)}
        className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-left"
      >
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          {hasAnyEnabled && (
            <span className="wsms-h-2 wsms-w-2 wsms-rounded-full wsms-bg-green-500" />
          )}
          <span className="wsms-text-[13px] wsms-font-medium">{form.name}</span>
        </div>
        {isExpanded ? (
          <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
        ) : (
          <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
        )}
      </button>

      {isExpanded && (
        <div className="wsms-p-4 wsms-space-y-6 wsms-border-t">
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label className="wsms-font-medium">{__('Notify admin')}</Label>
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-1">
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
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Multiple numbers can be separated with commas')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label>{__('Message')}</Label>
                  <Textarea
                    ref={messageFormRef}
                    value={messageForm}
                    onChange={(e) => setMessageForm(e.target.value)}
                    rows={3}
                    placeholder={__('New form submission received...')}
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

          {hasFields && (
            <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label className="wsms-font-medium">{__('Notify form submitter')}</Label>
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-1">
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
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>{__('Message')}</Label>
                    <Textarea
                      ref={messageFieldRef}
                      value={messageField}
                      onChange={(e) => setMessageField(e.target.value)}
                      rows={3}
                      placeholder={__('Thank you for your submission!')}
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
  const cf7Status = getPluginStatus('contact-form-7')

  // Formidable Forms
  const [formidableMetabox, setFormidableMetabox] = useSetting('formidable_metabox', '')
  const formidableStatus = getPluginStatus('formidable')

  // Forminator
  const forminatorData = getForminatorFormsData()
  const forminatorStatus = getPluginStatus('forminator')

  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields } = useAddonSettings('integrations')
  const cf7AddonFields = useAddonFieldsForSection('integrations', 'contact-form-7')

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

  const isSectionPluginActive = (sectionId) => {
    const lowerSectionId = sectionId.toLowerCase()
    for (const [pattern, pluginKey] of Object.entries(sectionPluginRequirements)) {
      if (lowerSectionId.includes(pattern)) {
        const status = getPluginStatus(pluginKey)
        return status.status === 'active'
      }
    }
    return true
  }

  const getSectionPluginInfo = (sectionId) => {
    const lowerSectionId = sectionId.toLowerCase()
    for (const [pattern, pluginKey] of Object.entries(sectionPluginRequirements)) {
      if (lowerSectionId.includes(pattern)) {
        return getPluginStatus(pluginKey)
      }
    }
    return null
  }

  // Additional integrations (Pro/Add-on only, excluding free ones already shown above)
  const additionalIntegrations = [
    { name: __('Gravity Forms'), pluginKey: 'gravity-forms', requirement: __('Requires WP SMS Pro'), Icon: ClipboardList },
    { name: __('Quform'), pluginKey: 'quform', requirement: __('Requires WP SMS Pro'), Icon: ClipboardList },
    { name: __('WooCommerce'), pluginKey: 'woocommerce', requirement: __('Requires WP SMS Pro'), Icon: ShoppingCart },
    { name: __('BuddyPress'), pluginKey: 'buddypress', requirement: __('Requires WP SMS Pro'), Icon: Users },
    { name: __('Easy Digital Downloads'), pluginKey: 'easy-digital-downloads', requirement: __('Requires WP SMS Pro'), Icon: CreditCard },
    { name: __('WP Job Manager'), pluginKey: 'wp-job-manager', requirement: __('Requires WP SMS Pro'), Icon: Briefcase },
    { name: __('Awesome Support'), pluginKey: 'awesome-support', requirement: __('Requires WP SMS Pro'), Icon: HeadphonesIcon },
    { name: __('Ultimate Member'), pluginKey: 'ultimate-member', requirement: __('Requires WP SMS Pro'), Icon: UserCircle },
    { name: __('Elementor Forms'), pluginKey: 'elementor-pro', requirement: __('Requires Elementor add-on'), Icon: Layers },
    { name: __('Fluent CRM'), pluginKey: 'fluent-crm', requirement: __('Requires Fluent add-on'), Icon: Mail },
    { name: __('Fluent Forms'), pluginKey: 'fluentform', requirement: __('Requires Fluent add-on'), Icon: ClipboardList },
    { name: __('Fluent Support'), pluginKey: 'fluent-support', requirement: __('Requires Fluent add-on'), Icon: HeadphonesIcon },
    { name: __('Paid Memberships Pro'), pluginKey: 'paid-memberships-pro', requirement: __('Requires Membership add-on'), Icon: CreditCard },
    { name: __('Simple Membership'), pluginKey: 'simple-membership', requirement: __('Requires Membership add-on'), Icon: UserCircle },
    { name: __('BookingPress'), pluginKey: 'bookingpress', requirement: __('Requires Booking add-on'), Icon: Calendar },
    { name: __('WooCommerce Appointments'), pluginKey: 'woocommerce-appointments', requirement: __('Requires Booking add-on'), Icon: Calendar },
    { name: __('WooCommerce Bookings'), pluginKey: 'woocommerce-bookings', requirement: __('Requires Booking add-on'), Icon: Calendar },
    { name: __('Booking Calendar'), pluginKey: 'booking', requirement: __('Requires Booking add-on'), Icon: Calendar },
  ]

  return (
    <div className="wsms-space-y-6">
      {/* Contact Form 7 */}
      <Card className={cf7Status.status !== 'active' ? 'wsms-opacity-75' : ''}>
        <CardHeader className="wsms-flex wsms-flex-row wsms-items-center wsms-justify-between wsms-space-y-0">
          <div>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <FileInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Contact Form 7')}
            </CardTitle>
            <CardDescription className="wsms-mt-1">
              {__('Send SMS notifications when Contact Form 7 forms are submitted')}
            </CardDescription>
          </div>
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <PluginStatusBadge status={cf7Status.status} />
            {cf7Status.status !== 'active' && (
              <PluginActionLink
                status={cf7Status.status}
                actionUrl={cf7Status.actionUrl}
                isExternal={cf7Status.isExternal}
                pluginName="Contact Form 7"
              />
            )}
          </div>
        </CardHeader>
        {cf7Status.status === 'active' && (
          <CardContent className="wsms-border-t wsms-pt-4">
            <div className="wsms-rounded-lg wsms-border wsms-p-4">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <h4 className="wsms-text-[13px] wsms-font-medium">{__('SMS Notification Tab')}</h4>
                  <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-1">
                    {__('Add an SMS notification settings tab to each Contact Form 7 form editor.')}
                  </p>
                </div>
                <Switch
                  checked={cf7Metabox === '1'}
                  onCheckedChange={(checked) => setCf7Metabox(checked ? '1' : '')}
                />
              </div>
            </div>
            {cf7AddonFields.length > 0 && (
              <div className="wsms-mt-4">
                <AddonFieldsInjection fields={cf7AddonFields} />
              </div>
            )}
          </CardContent>
        )}
      </Card>

      {/* Formidable Forms */}
      <Card className={formidableStatus.status !== 'active' ? 'wsms-opacity-75' : ''}>
        <CardHeader className="wsms-flex wsms-flex-row wsms-items-center wsms-justify-between wsms-space-y-0">
          <div>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <FileInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Formidable Forms')}
            </CardTitle>
            <CardDescription className="wsms-mt-1">
              {__('Send SMS notifications when Formidable forms are submitted')}
            </CardDescription>
          </div>
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <PluginStatusBadge status={formidableStatus.status} />
            {formidableStatus.status !== 'active' && (
              <PluginActionLink
                status={formidableStatus.status}
                actionUrl={formidableStatus.actionUrl}
                isExternal={formidableStatus.isExternal}
                pluginName="Formidable Forms"
              />
            )}
          </div>
        </CardHeader>
        {formidableStatus.status === 'active' && (
          <CardContent className="wsms-border-t wsms-pt-4">
            <div className="wsms-rounded-lg wsms-border wsms-p-4">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <h4 className="wsms-text-[13px] wsms-font-medium">{__('SMS Notification Tab')}</h4>
                  <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-1">
                    {__('Add an SMS notification settings tab to each Formidable form editor.')}
                  </p>
                </div>
                <Switch
                  checked={formidableMetabox === '1'}
                  onCheckedChange={(checked) => setFormidableMetabox(checked ? '1' : '')}
                />
              </div>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Forminator */}
      <Card className={forminatorStatus.status !== 'active' ? 'wsms-opacity-75' : ''}>
        <CardHeader className="wsms-flex wsms-flex-row wsms-items-center wsms-justify-between wsms-space-y-0">
          <div>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <FileInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Forminator')}
            </CardTitle>
            <CardDescription className="wsms-mt-1">
              {__('Send SMS notifications when Forminator forms are submitted')}
            </CardDescription>
          </div>
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <PluginStatusBadge status={forminatorStatus.status} />
            {forminatorStatus.status !== 'active' && (
              <PluginActionLink
                status={forminatorStatus.status}
                actionUrl={forminatorStatus.actionUrl}
                isExternal={forminatorStatus.isExternal}
                pluginName="Forminator"
              />
            )}
          </div>
        </CardHeader>
        <CardContent className={forminatorStatus.status === 'active' ? 'wsms-border-t wsms-pt-4' : ''}>
          {forminatorStatus.status !== 'active' ? (
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                {__('Install and activate Forminator to configure SMS notifications.')}
              </p>
            </div>
          ) : forminatorData.isActive && forminatorData.forms.length > 0 ? (
            <div className="wsms-space-y-2">
              {forminatorData.forms.map((form) => (
                <ForminatorFormSettings key={form.id} form={form} />
              ))}
            </div>
          ) : (
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                {__('No forms found. Create a form in Forminator to configure SMS notifications.')}
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Add-on Defined Sections */}
      {addonSections.map((section) => {
        const isPluginActive = isSectionPluginActive(section.id)
        const pluginInfo = getSectionPluginInfo(section.id)

        if (!isPluginActive && pluginInfo) {
          return (
            <Card key={section.id} className="wsms-opacity-75">
              <CardHeader>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <Puzzle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {section.title}
                </CardTitle>
                {section.description && (
                  <CardDescription>{section.description}</CardDescription>
                )}
              </CardHeader>
              <CardContent>
                <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4">
                  <div className="wsms-flex wsms-items-center wsms-justify-between">
                    <PluginStatusBadge status={pluginInfo.status} />
                    <PluginActionLink
                      status={pluginInfo.status}
                      actionUrl={pluginInfo.actionUrl}
                      isExternal={pluginInfo.isExternal}
                      pluginName={pluginInfo.name}
                    />
                  </div>
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

      {/* Standalone Add-on Fields */}
      {standaloneFields.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Puzzle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Additional Settings')}
            </CardTitle>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            {standaloneFields.map((field) => (
              <DynamicField key={field.id} field={field} />
            ))}
          </CardContent>
        </Card>
      )}

      {/* Additional Integrations */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Puzzle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Additional Integrations')}
          </CardTitle>
          <CardDescription>
            {__('Other plugins supported through WP SMS Pro and add-ons')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-grid wsms-grid-cols-1 md:wsms-grid-cols-2 lg:wsms-grid-cols-3 wsms-gap-3">
            {additionalIntegrations.map((plugin) => {
              const pluginStatus = getPluginStatus(plugin.pluginKey)
              const isActive = pluginStatus.status === 'active'
              const IconComponent = plugin.Icon

              return (
                <div
                  key={plugin.pluginKey}
                  className={`wsms-rounded-lg wsms-border wsms-p-3 ${!isActive ? 'wsms-bg-muted/30' : ''}`}
                >
                  <div className="wsms-flex wsms-items-center wsms-justify-between">
                    <div className="wsms-flex wsms-items-center wsms-gap-2">
                      <IconComponent className={`wsms-h-4 wsms-w-4 ${isActive ? 'wsms-text-primary' : 'wsms-text-muted-foreground'}`} />
                      <span className="wsms-font-medium wsms-text-xs">{plugin.name}</span>
                    </div>
                    <PluginStatusBadge status={pluginStatus.status} />
                  </div>
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-1 wsms-ml-6">{plugin.requirement}</p>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
