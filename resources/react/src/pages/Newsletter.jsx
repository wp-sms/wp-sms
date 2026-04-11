import { __ } from '@wordpress/i18n'
import React from 'react'
import { FormInput, Shield, Mail, Palette } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { SettingRow, SelectField, MultiSelectField } from '@/components/ui/form-field'
import { TemplateTextarea } from '@/components/shared/TemplateTextarea'
import { useSetting } from '@/context/SettingsContext'
import { getWpSettings } from '@/lib/utils'

export default function Newsletter() {
  const { groups: rawGroups = [], gdprEnabled = false } = getWpSettings()

  // Transform groups array to format expected by MultiSelect: [{value, label}]
  const groupOptions = Array.isArray(rawGroups)
    ? rawGroups.map(g => ({ value: String(g.id), label: g.name }))
    : []

  // Form settings
  const [formGroups, setFormGroups] = useSetting('newsletter_form_groups', '')
  const [specifiedGroups, setSpecifiedGroups] = useSetting('newsletter_form_specified_groups', [])
  const [multipleSelect, setMultipleSelect] = useSetting('newsletter_form_multiple_select', '')
  const [defaultGroup, setDefaultGroup] = useSetting('newsletter_form_default_group', '0')
  const [formVerify, setFormVerify] = useSetting('newsletter_form_verify', '')

  // Welcome message
  const [welcomeEnabled, setWelcomeEnabled] = useSetting('newsletter_form_welcome', '')
  const [welcomeText, setWelcomeText] = useSetting('newsletter_form_welcome_text', '')

  // Styling
  const [disableStyle, setDisableStyle] = useSetting('disable_style_in_front', '')

  // GDPR (if enabled in Phone settings)
  const [gdprText, setGdprText] = useSetting('newsletter_form_gdpr_text', '')
  const [gdprCheckbox, setGdprCheckbox] = useSetting('newsletter_form_gdpr_confirm_checkbox', 'unchecked')

  const showGroups = formGroups === '1'

  return (
    <div className="wsms-space-y-6">
      {/* Subscription Form */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <FormInput className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('SMS Newsletter Configuration', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Configure how visitors subscribe to your SMS notifications', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Show Groups in Form', 'wp-sms')}
            description={__('Let subscribers choose which groups to join.', 'wp-sms')}
            checked={showGroups}
            onCheckedChange={(checked) => setFormGroups(checked ? '1' : '')}
          />

          {showGroups && (
            <>
              <SettingRow
                title={__('Allow Multiple Group Selection', 'wp-sms')}
                description={__('Allow subscribers to join multiple groups from the form.', 'wp-sms')}
                checked={multipleSelect === '1'}
                onCheckedChange={(checked) => setMultipleSelect(checked ? '1' : '')}
              />

              <MultiSelectField
                label={__('Available Groups', 'wp-sms')}
                options={groupOptions}
                value={specifiedGroups}
                onValueChange={setSpecifiedGroups}
                placeholder={__('All groups', 'wp-sms')}
                searchPlaceholder={__('Search groups...', 'wp-sms')}
                description={__('Which groups subscribers can choose from. Leave empty for all groups.', 'wp-sms')}
              />

              {multipleSelect !== '1' && (
                <SelectField
                  label={__('Default Group', 'wp-sms')}
                  value={defaultGroup}
                  onValueChange={setDefaultGroup}
                  placeholder={__('Select a group', 'wp-sms')}
                  description={__('Automatically add new subscribers to this group.', 'wp-sms')}
                  options={[
                    { value: '0', label: __('All', 'wp-sms') },
                    ...groupOptions,
                  ]}
                />
              )}
            </>
          )}

          <SettingRow
            title={__('Require SMS Verification', 'wp-sms')}
            description={__('Subscribers must verify their phone number via SMS code.', 'wp-sms')}
            checked={formVerify === '1'}
            onCheckedChange={(checked) => setFormVerify(checked ? '1' : '')}
          />
        </CardContent>
      </Card>

      {/* Welcome Message */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Mail className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Welcome SMS', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Set up automatic SMS messages for new subscribers', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Send Welcome Message', 'wp-sms')}
            description={__('Automatically send a welcome SMS to new subscribers.', 'wp-sms')}
            checked={welcomeEnabled === '1'}
            onCheckedChange={(checked) => setWelcomeEnabled(checked ? '1' : '')}
          />

          {welcomeEnabled === '1' && (
            <div className="wsms-space-y-2">
              <Label htmlFor="welcomeText">{__('Welcome Message', 'wp-sms')}</Label>
              <TemplateTextarea
                id="welcomeText"
                value={welcomeText}
                onChange={setWelcomeText}
                placeholder={__('Welcome to our newsletter! Thanks for subscribing.', 'wp-sms')}
                rows={3}
                variables={['%subscriber_name%', '%subscriber_mobile%', '%subscriber_status%', '%subscriber_group%', '%subscriber_custom_fields%', '%subscriber_date%', '%unsubscribe_url%']}
              />
            </div>
          )}
        </CardContent>
      </Card>

      {/* Appearance */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Palette className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Form Appearance', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Customize the look of your subscription form', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Disable Default Styles', 'wp-sms')}
            description={__('Remove plugin CSS to use your own form styling.', 'wp-sms')}
            checked={disableStyle === '1'}
            onCheckedChange={(checked) => setDisableStyle(checked ? '1' : '')}
          />
        </CardContent>
      </Card>

      {/* GDPR Compliance */}
      {gdprEnabled && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('GDPR Settings', 'wp-sms')}
            </CardTitle>
            <CardDescription>
              {__('Configure privacy consent for newsletter subscriptions', 'wp-sms')}
            </CardDescription>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            <div className="wsms-space-y-2">
              <Label htmlFor="gdprText">{__('Consent Message', 'wp-sms')}</Label>
              <Textarea
                id="gdprText"
                value={gdprText}
                onChange={(e) => setGdprText(e.target.value)}
                placeholder={__('I agree to receive SMS notifications and understand that my data will be handled according to the privacy policy.', 'wp-sms')}
                rows={3}
              />
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                {__('Privacy consent text shown to subscribers. Required for GDPR compliance.', 'wp-sms')}
              </p>
            </div>

            <SelectField
              label={__('Checkbox Default State', 'wp-sms')}
              value={gdprCheckbox}
              onValueChange={setGdprCheckbox}
              placeholder={__('Select default state', 'wp-sms')}
              description={__('Must be unchecked by default for GDPR compliance.', 'wp-sms')}
              options={[
                { value: 'checked', label: __('Checked', 'wp-sms') },
                { value: 'unchecked', label: __('Unchecked', 'wp-sms') },
              ]}
            />
          </CardContent>
        </Card>
      )}

      {!gdprEnabled && (
        <Card className="wsms-border-amber-200 wsms-bg-amber-50 dark:wsms-border-amber-900 dark:wsms-bg-amber-950/30">
          <CardContent className="wsms-p-4">
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              {__('To enable GDPR settings for newsletters, first enable "GDPR Compliance Enhancements" in the Phone Configuration page.', 'wp-sms')}
            </p>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
