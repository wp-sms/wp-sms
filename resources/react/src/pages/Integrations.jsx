import React, { useState, useRef } from 'react'
import { Puzzle, ChevronDown, ChevronUp, ShoppingCart, Users, CreditCard, Briefcase, HeadphonesIcon, UserCircle, Mail, ClipboardList, Calendar, Layers, FileInput } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'
import { useSetting, useProSetting } from '@/context/SettingsContext'
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
    inactive: { label: __('Inactive'), className: 'wsms-bg-red-50 wsms-text-red-700 wsms-border-red-200' },
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

// Get Forminator forms data
const getForminatorFormsData = () => {
  const wpSettings = getWpSettings()
  return wpSettings.forminatorForms || { isActive: false, forms: [] }
}

// Get Gravity Forms data
const getGravityFormsData = () => {
  const wpSettings = getWpSettings()
  return wpSettings.gravityForms || { isActive: false, forms: [] }
}

// Get Quform data
const getQuformFormsData = () => {
  const wpSettings = getWpSettings()
  return wpSettings.quformForms || { isActive: false, forms: [] }
}

// Clickable variable chip component - matches TemplateTextarea styling
const VariableChip = ({ variable, onClick }) => (
  <button
    type="button"
    onClick={() => onClick(variable.key)}
    className="wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/30 hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary wsms-transition-colors wsms-cursor-pointer focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20"
    title={variable.label || variable.key}
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
        className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-start"
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
          {/* Send SMS to a number */}
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label className="wsms-font-medium">{__('Send SMS to a number')}</Label>
              </div>
              <Switch
                checked={enableForm === '1'}
                onCheckedChange={(checked) => setEnableForm(checked ? '1' : '')}
              />
            </div>

            {enableForm === '1' && (
              <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                <div className="wsms-space-y-2">
                  <Label>{__('Phone number(s)')}</Label>
                  <Input
                    type="text"
                    value={receiverForm}
                    onChange={(e) => setReceiverForm(e.target.value)}
                    placeholder="+1234567890, +0987654321"
                  />
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label>{__('Message body')}</Label>
                  <Textarea
                    ref={messageFormRef}
                    value={messageForm}
                    onChange={(e) => setMessageForm(e.target.value)}
                    rows={3}
                    placeholder={__('Enter your message content.')}
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

          {/* Send SMS to field */}
          {hasFields && (
            <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label className="wsms-font-medium">{__('Send SMS to field')}</Label>
                </div>
                <Switch
                  checked={enableField === '1'}
                  onCheckedChange={(checked) => setEnableField(checked ? '1' : '')}
                />
              </div>

              {enableField === '1' && (
                <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                  <div className="wsms-space-y-2">
                    <Label>{__('A field of the form')}</Label>
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
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {__('Select the field of your form.')}
                    </p>
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>{__('Message body')}</Label>
                    <Textarea
                      ref={messageFieldRef}
                      value={messageField}
                      onChange={(e) => setMessageField(e.target.value)}
                      rows={3}
                      placeholder={__('Enter your message content.')}
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

// Gravity Forms form settings component
const GravityFormSettings = ({ form }) => {
  const [isExpanded, setIsExpanded] = useState(false)
  const messageFormRef = useRef(null)
  const messageFieldRef = useRef(null)

  // Pro settings stored in wps_pp_settings
  const [enableForm, setEnableForm] = useProSetting(`gf_notify_enable_form_${form.id}`, '')
  const [receiverForm, setReceiverForm] = useProSetting(`gf_notify_receiver_form_${form.id}`, '')
  const [messageForm, setMessageForm] = useProSetting(`gf_notify_message_form_${form.id}`, '')
  const [enableField, setEnableField] = useProSetting(`gf_notify_enable_field_form_${form.id}`, '')
  const [receiverField, setReceiverField] = useProSetting(`gf_notify_receiver_field_form_${form.id}`, '')
  const [messageField, setMessageField] = useProSetting(`gf_notify_message_field_form_${form.id}`, '')

  // Use hasFields from PHP which indicates if form has selectable fields
  const hasFields = form.hasFields || (form.fields && Object.keys(form.fields).length > 0)
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
        className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-start"
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
          {/* Send SMS to a number */}
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label className="wsms-font-medium">{__('Send SMS to a number')}</Label>
              </div>
              <Switch
                checked={enableForm === '1'}
                onCheckedChange={(checked) => setEnableForm(checked ? '1' : '')}
              />
            </div>

            {enableForm === '1' && (
              <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                <div className="wsms-space-y-2">
                  <Label>{__('Phone number(s)')}</Label>
                  <Input
                    type="text"
                    value={receiverForm}
                    onChange={(e) => setReceiverForm(e.target.value)}
                    placeholder="+1234567890, +0987654321"
                  />
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label>{__('Message body')}</Label>
                  <Textarea
                    ref={messageFormRef}
                    value={messageForm}
                    onChange={(e) => setMessageForm(e.target.value)}
                    rows={3}
                    placeholder={__('Enter your message content.')}
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

          {/* Send SMS to field */}
          {hasFields && (
            <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label className="wsms-font-medium">{__('Send SMS to field')}</Label>
                </div>
                <Switch
                  checked={enableField === '1'}
                  onCheckedChange={(checked) => setEnableField(checked ? '1' : '')}
                />
              </div>

              {enableField === '1' && (
                <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                  <div className="wsms-space-y-2">
                    <Label>{__('A field of the form')}</Label>
                    <Select value={receiverField} onValueChange={setReceiverField}>
                      <SelectTrigger>
                        <SelectValue placeholder={__('Select a field...')} />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(form.fields).map(([id, label]) => (
                          <SelectItem key={id} value={id}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {__('Select the field of your form.')}
                    </p>
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>{__('Message body')}</Label>
                    <Textarea
                      ref={messageFieldRef}
                      value={messageField}
                      onChange={(e) => setMessageField(e.target.value)}
                      rows={3}
                      placeholder={__('Enter your message content.')}
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

// Quform form settings component
const QuformFormSettings = ({ form }) => {
  const [isExpanded, setIsExpanded] = useState(false)
  const messageFormRef = useRef(null)
  const messageFieldRef = useRef(null)

  // Pro settings stored in wps_pp_settings
  const [enableForm, setEnableForm] = useProSetting(`qf_notify_enable_form_${form.id}`, '')
  const [receiverForm, setReceiverForm] = useProSetting(`qf_notify_receiver_form_${form.id}`, '')
  const [messageForm, setMessageForm] = useProSetting(`qf_notify_message_form_${form.id}`, '')
  const [enableField, setEnableField] = useProSetting(`qf_notify_enable_field_form_${form.id}`, '')
  const [receiverField, setReceiverField] = useProSetting(`qf_notify_receiver_field_form_${form.id}`, '')
  const [messageField, setMessageField] = useProSetting(`qf_notify_message_field_form_${form.id}`, '')

  // Use hasElements from PHP which indicates if form has elements (for "Send to field" feature)
  // This matches the legacy code which checks $form['elements'] directly
  const hasFields = form.hasElements || (form.fields && Object.keys(form.fields).length > 0)
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
        className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-start"
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
          {/* Send SMS to a number */}
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label className="wsms-font-medium">{__('Send SMS to a number')}</Label>
              </div>
              <Switch
                checked={enableForm === '1'}
                onCheckedChange={(checked) => setEnableForm(checked ? '1' : '')}
              />
            </div>

            {enableForm === '1' && (
              <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                <div className="wsms-space-y-2">
                  <Label>{__('Phone number(s)')}</Label>
                  <Input
                    type="text"
                    value={receiverForm}
                    onChange={(e) => setReceiverForm(e.target.value)}
                    placeholder="+1234567890, +0987654321"
                  />
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label>{__('Message body')}</Label>
                  <Textarea
                    ref={messageFormRef}
                    value={messageForm}
                    onChange={(e) => setMessageForm(e.target.value)}
                    rows={3}
                    placeholder={__('Enter your message content.')}
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

          {/* Send SMS to field */}
          {hasFields && (
            <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label className="wsms-font-medium">{__('Send SMS to field')}</Label>
                </div>
                <Switch
                  checked={enableField === '1'}
                  onCheckedChange={(checked) => setEnableField(checked ? '1' : '')}
                />
              </div>

              {enableField === '1' && (
                <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                  <div className="wsms-space-y-2">
                    <Label>{__('A field of the form')}</Label>
                    <Select value={receiverField} onValueChange={setReceiverField}>
                      <SelectTrigger>
                        <SelectValue placeholder={__('Select a field...')} />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(form.fields).map(([id, label]) => (
                          <SelectItem key={id} value={id}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {__('Select the field of your form.')}
                    </p>
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>{__('Message body')}</Label>
                    <Textarea
                      ref={messageFieldRef}
                      value={messageField}
                      onChange={(e) => setMessageField(e.target.value)}
                      rows={3}
                      placeholder={__('Enter your message content.')}
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

// Fluent Forms form settings component
const FluentFormSettings = ({ form }) => {
  const [isExpanded, setIsExpanded] = useState(false)
  const messageFormRef = useRef(null)
  const messageFieldRef = useRef(null)

  const [enableForm, setEnableForm] = useSetting(`fluent_forms_notif_after_submission_${form.id}`, '')
  const [receiverForm, setReceiverForm] = useSetting(`fluent_forms_notif_after_submission_${form.id}_receiver`, '')
  const [messageForm, setMessageForm] = useSetting(`fluent_forms_notif_after_submission_${form.id}_message`, '')
  const [enableField, setEnableField] = useSetting(`fluent_forms_notif_field_after_submission_${form.id}`, '')
  const [receiverField, setReceiverField] = useSetting(`fluent_forms_notif_field_after_submission_${form.id}_field`, '')
  const [messageField, setMessageField] = useSetting(`fluent_forms_notif_field_after_submission_${form.id}_message`, '')

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
        className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-start"
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
          {/* Send SMS to a number */}
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label className="wsms-font-medium">{__('Send SMS to a number')}</Label>
              </div>
              <Switch
                checked={enableForm === '1'}
                onCheckedChange={(checked) => setEnableForm(checked ? '1' : '')}
              />
            </div>

            {enableForm === '1' && (
              <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                <div className="wsms-space-y-2">
                  <Label>{__('Phone number(s)')}</Label>
                  <Input
                    type="text"
                    value={receiverForm}
                    onChange={(e) => setReceiverForm(e.target.value)}
                    placeholder="+1234567890, +0987654321"
                  />
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label>{__('Message body')}</Label>
                  <Textarea
                    ref={messageFormRef}
                    value={messageForm}
                    onChange={(e) => setMessageForm(e.target.value)}
                    rows={3}
                    placeholder={__('Enter your message content.')}
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

          {/* Send SMS to field */}
          {hasFields && (
            <div className="wsms-space-y-4 wsms-pt-4 wsms-border-t">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label className="wsms-font-medium">{__('Send SMS to field')}</Label>
                </div>
                <Switch
                  checked={enableField === '1'}
                  onCheckedChange={(checked) => setEnableField(checked ? '1' : '')}
                />
              </div>

              {enableField === '1' && (
                <div className="wsms-space-y-4 wsms-ps-4 wsms-border-s-2 wsms-border-primary/20">
                  <div className="wsms-space-y-2">
                    <Label>{__('A field of the form')}</Label>
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
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {__('Select the field of your form.')}
                    </p>
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>{__('Message body')}</Label>
                    <Textarea
                      ref={messageFieldRef}
                      value={messageField}
                      onChange={(e) => setMessageField(e.target.value)}
                      rows={3}
                      placeholder={__('Enter your message content.')}
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

  // Gravity Forms
  const gravityFormsData = getGravityFormsData()
  const gravityFormsStatus = getPluginStatus('gravity-forms')

  // Quform
  const quformData = getQuformFormsData()
  const quformStatus = getPluginStatus('quform')

  // Fluent Forms
  const fluentFormsData = (() => {
    const wpSettings = getWpSettings()
    return wpSettings.fluentForms || { isActive: false, forms: [] }
  })()
  const fluentFormsStatus = getPluginStatus('fluentform')

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
    'woo-appointments': 'woocommerce-appointments',
    'woocommerce-appointments': 'woocommerce-appointments',
    'woo-bookings': 'woocommerce-bookings',
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

  // All integrations (free + pro)
  const additionalIntegrations = [
    { name: __('Contact Form 7'), pluginKey: 'contact-form-7', requirement: __('Free'), Icon: FileInput },
    { name: __('Formidable Forms'), pluginKey: 'formidable', requirement: __('Free'), Icon: FileInput },
    { name: __('Forminator'), pluginKey: 'forminator', requirement: __('Free'), Icon: FileInput },
    { name: __('Gravity Forms'), pluginKey: 'gravity-forms', requirement: __('Requires WSMS PRO'), Icon: ClipboardList },
    { name: __('Quform'), pluginKey: 'quform', requirement: __('Requires WSMS PRO'), Icon: ClipboardList },
    { name: __('WooCommerce'), pluginKey: 'woocommerce', requirement: __('Requires WSMS PRO'), Icon: ShoppingCart },
    { name: __('BuddyPress'), pluginKey: 'buddypress', requirement: __('Requires WSMS PRO'), Icon: Users },
    { name: __('Easy Digital Downloads'), pluginKey: 'easy-digital-downloads', requirement: __('Requires WSMS PRO'), Icon: CreditCard },
    { name: __('WP Job Manager'), pluginKey: 'wp-job-manager', requirement: __('Requires WSMS PRO'), Icon: Briefcase },
    { name: __('Awesome Support'), pluginKey: 'awesome-support', requirement: __('Requires WSMS PRO'), Icon: HeadphonesIcon },
    { name: __('Ultimate Member'), pluginKey: 'ultimate-member', requirement: __('Requires WSMS PRO'), Icon: UserCircle },
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

  // Sort additional integrations: active first, inactive second, not_installed last
  const statusOrder = { active: 0, inactive: 1, not_installed: 2, unknown: 3 }
  const sortedAdditionalIntegrations = [...additionalIntegrations].sort((a, b) => {
    const aStatus = getPluginStatus(a.pluginKey).status
    const bStatus = getPluginStatus(b.pluginKey).status
    return (statusOrder[aStatus] ?? 3) - (statusOrder[bStatus] ?? 3)
  })

  // Sort addon sections: active first, then inactive/not installed
  const sortedAddonSections = [...addonSections].sort((a, b) => {
    const aActive = isSectionPluginActive(a.id) ? 0 : 1
    const bActive = isSectionPluginActive(b.id) ? 0 : 1
    return aActive - bActive
  })


  // Collapsible state for integrations
  const [cf7Open, setCf7Open] = useState(false)
  const [formidableOpen, setFormidableOpen] = useState(false)
  const [forminatorOpen, setForminatorOpen] = useState(false)
  const [gravityFormsOpen, setGravityFormsOpen] = useState(false)
  const [quformOpen, setQuformOpen] = useState(false)
  const [fluentFormsOpen, setFluentFormsOpen] = useState(false)

  return (
    <div className="wsms-space-y-6">
      {/* Contact Form 7 - only show if active */}
      {cf7Status.status === 'active' && (
        <Card>
          <CardHeader
            className="wsms-cursor-pointer wsms-select-none"
            onClick={() => setCf7Open(!cf7Open)}
          >
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <FileInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {__('Contact Form 7')}
                </CardTitle>
                <CardDescription className="wsms-mt-1">
                  {__('Send SMS notifications when Contact Form 7 forms are submitted')}
                </CardDescription>
              </div>
              {cf7Open ? (
                <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              ) : (
                <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              )}
            </div>
          </CardHeader>
          {cf7Open && (
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
      )}

      {/* Formidable Forms - only show if active */}
      {formidableStatus.status === 'active' && (
        <Card>
          <CardHeader
            className="wsms-cursor-pointer wsms-select-none"
            onClick={() => setFormidableOpen(!formidableOpen)}
          >
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <FileInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {__('Formidable Forms')}
                </CardTitle>
                <CardDescription className="wsms-mt-1">
                  {__('Send SMS notifications when Formidable forms are submitted')}
                </CardDescription>
              </div>
              {formidableOpen ? (
                <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              ) : (
                <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              )}
            </div>
          </CardHeader>
          {formidableOpen && (
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
      )}

      {/* Forminator - only show if active */}
      {forminatorStatus.status === 'active' && (
        <Card>
          <CardHeader
            className="wsms-cursor-pointer wsms-select-none"
            onClick={() => setForminatorOpen(!forminatorOpen)}
          >
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <FileInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {__('Forminator')}
                </CardTitle>
                <CardDescription className="wsms-mt-1">
                  {__('Send SMS notifications when Forminator forms are submitted')}
                </CardDescription>
              </div>
              {forminatorOpen ? (
                <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              ) : (
                <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              )}
            </div>
          </CardHeader>
          {forminatorOpen && (
            <CardContent className="wsms-border-t wsms-pt-4">
              {forminatorData.isActive && forminatorData.forms.length > 0 ? (
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
          )}
        </Card>
      )}

      {/* Gravity Forms - only show if active AND Pro is active */}
      {gravityFormsStatus.status === 'active' && window.wpSmsSettings?.addons?.pro && (
        <Card>
          <CardHeader
            className="wsms-cursor-pointer wsms-select-none"
            onClick={() => setGravityFormsOpen(!gravityFormsOpen)}
          >
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <ClipboardList className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {__('Gravity Forms')}
                </CardTitle>
                <CardDescription className="wsms-mt-1">
                  {__('Send SMS notifications when Gravity Forms are submitted')}
                </CardDescription>
              </div>
              {gravityFormsOpen ? (
                <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              ) : (
                <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              )}
            </div>
          </CardHeader>
          {gravityFormsOpen && (
            <CardContent className="wsms-border-t wsms-pt-4">
              {gravityFormsData.isActive && gravityFormsData.forms.length > 0 ? (
                <div className="wsms-space-y-2">
                  {gravityFormsData.forms.map((form) => (
                    <GravityFormSettings key={form.id} form={form} />
                  ))}
                </div>
              ) : (
                <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('No forms found. Create a form in Gravity Forms to configure SMS notifications.')}
                  </p>
                </div>
              )}
            </CardContent>
          )}
        </Card>
      )}

      {/* Quform - only show if active AND Pro is active */}
      {quformStatus.status === 'active' && window.wpSmsSettings?.addons?.pro && (
        <Card>
          <CardHeader
            className="wsms-cursor-pointer wsms-select-none"
            onClick={() => setQuformOpen(!quformOpen)}
          >
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <ClipboardList className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {__('Quform')}
                </CardTitle>
                <CardDescription className="wsms-mt-1">
                  {__('Send SMS notifications when Quform forms are submitted')}
                </CardDescription>
              </div>
              {quformOpen ? (
                <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              ) : (
                <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              )}
            </div>
          </CardHeader>
          {quformOpen && (
            <CardContent className="wsms-border-t wsms-pt-4">
              {quformData.isActive && quformData.forms.length > 0 ? (
                <div className="wsms-space-y-2">
                  {quformData.forms.map((form) => (
                    <QuformFormSettings key={form.id} form={form} />
                  ))}
                </div>
              ) : (
                <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('No forms found. Create a form in Quform to configure SMS notifications.')}
                  </p>
                </div>
              )}
            </CardContent>
          )}
        </Card>
      )}

      {/* Fluent Forms - only show if active AND Fluent add-on is active */}
      {fluentFormsStatus.status === 'active' && window.wpSmsSettings?.addons?.fluent && (
        <Card>
          <CardHeader
            className="wsms-cursor-pointer wsms-select-none"
            onClick={() => setFluentFormsOpen(!fluentFormsOpen)}
          >
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                  <ClipboardList className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  {__('Fluent Forms')}
                </CardTitle>
                <CardDescription className="wsms-mt-1">
                  {__('Send SMS notifications when Fluent Forms are submitted')}
                </CardDescription>
              </div>
              {fluentFormsOpen ? (
                <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              ) : (
                <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              )}
            </div>
          </CardHeader>
          {fluentFormsOpen && (
            <CardContent className="wsms-border-t wsms-pt-4">
              {fluentFormsData.isActive && fluentFormsData.forms.length > 0 ? (
                <div className="wsms-space-y-2">
                  {fluentFormsData.forms.map((form) => (
                    <FluentFormSettings key={form.id} form={form} />
                  ))}
                </div>
              ) : (
                <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('No forms found. Create a form in Fluent Forms to configure SMS notifications.')}
                  </p>
                </div>
              )}
            </CardContent>
          )}
        </Card>
      )}

      {/* Add-on Defined Sections - sorted: active first */}
      {sortedAddonSections.map((section) => (
        <AddonSection
          key={section.id}
          section={section}
          fields={fieldsBySection[section.id] || []}
        />
      ))}

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
            {__('Integrations')}
          </CardTitle>
          <CardDescription>
            {__('All supported plugins and integrations')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-grid wsms-grid-cols-1 md:wsms-grid-cols-2 lg:wsms-grid-cols-3 wsms-gap-3">
            {sortedAdditionalIntegrations.map((plugin) => {
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
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-1 wsms-ms-6">{plugin.requirement}</p>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
