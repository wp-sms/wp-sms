import React from 'react'
import { Users, FormInput, Shield, Mail, Palette } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { MultiSelect } from '@/components/ui/multi-select'
import { useSetting } from '@/context/SettingsContext'
import { getWpSettings, __ } from '@/lib/utils'

export default function Newsletter() {
  const { groups: rawGroups = [], gdprEnabled = false } = getWpSettings()

  // Transform groups array to format expected by MultiSelect: [{value, label}]
  const groupOptions = Array.isArray(rawGroups)
    ? rawGroups.map(g => ({ value: String(g.id), label: g.name }))
    : []

  // Form settings
  const [formGroups, setFormGroups] = useSetting('newsletter_form_groups', '')
  const [multipleSelect, setMultipleSelect] = useSetting('newsletter_form_multiple_select', '')
  const [specifiedGroups, setSpecifiedGroups] = useSetting('newsletter_form_specified_groups', [])
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
            <FormInput className="wsms-h-5 wsms-w-5" />
            {__('SMS Newsletter Configuration')}
          </CardTitle>
          <CardDescription>
            {__('Configure how visitors subscribe to your SMS notifications')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Show Groups in Form')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Let subscribers choose which groups to join.')}
              </p>
            </div>
            <Switch
              checked={showGroups}
              onCheckedChange={(checked) => setFormGroups(checked ? '1' : '')}
              aria-label={__('Show groups in form')}
            />
          </div>

          {showGroups && (
            <>
              <div className="wsms-space-y-2">
                <Label>{__('Available Groups')}</Label>
                <MultiSelect
                  options={groupOptions}
                  value={specifiedGroups}
                  onValueChange={setSpecifiedGroups}
                  placeholder={__('All groups')}
                  searchPlaceholder={__('Search groups...')}
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  {__('Which groups subscribers can choose from. Leave empty for all groups.')}
                </p>
              </div>

              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
                <div>
                  <p className="wsms-font-medium">{__('Allow Multiple Groups')}</p>
                  <p className="wsms-text-sm wsms-text-muted-foreground">
                    {__('Let subscribers join more than one group at a time.')}
                  </p>
                </div>
                <Switch
                  checked={multipleSelect === '1'}
                  onCheckedChange={(checked) => setMultipleSelect(checked ? '1' : '')}
                  aria-label={__('Allow multiple groups')}
                />
              </div>

              <div className="wsms-space-y-2">
                <Label>{__('Default Group')}</Label>
                <Select value={defaultGroup} onValueChange={setDefaultGroup}>
                  <SelectTrigger aria-label={__('Default group')}>
                    <SelectValue placeholder={__('Select a group')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{__('All')}</SelectItem>
                    {Array.isArray(rawGroups) && rawGroups.map((group) => (
                      <SelectItem key={group.id} value={String(group.id)}>
                        {group.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  {__('Automatically add new subscribers to this group.')}
                </p>
              </div>
            </>
          )}

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Require SMS Verification')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Subscribers must verify their phone number via SMS code.')}
              </p>
            </div>
            <Switch
              checked={formVerify === '1'}
              onCheckedChange={(checked) => setFormVerify(checked ? '1' : '')}
              aria-label={__('Require SMS verification')}
            />
          </div>
        </CardContent>
      </Card>

      {/* Welcome Message */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Mail className="wsms-h-5 wsms-w-5" />
            {__('Welcome SMS')}
          </CardTitle>
          <CardDescription>
            {__('Set up automatic SMS messages for new subscribers')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Send Welcome Message')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Automatically send a welcome SMS to new subscribers.')}
              </p>
            </div>
            <Switch
              checked={welcomeEnabled === '1'}
              onCheckedChange={(checked) => setWelcomeEnabled(checked ? '1' : '')}
              aria-label={__('Send welcome message')}
            />
          </div>

          {welcomeEnabled === '1' && (
            <div className="wsms-space-y-2">
              <Label htmlFor="welcomeText">{__('Welcome Message')}</Label>
              <Textarea
                id="welcomeText"
                value={welcomeText}
                onChange={(e) => setWelcomeText(e.target.value)}
                placeholder={__('Welcome to our newsletter! Thanks for subscribing.')}
                rows={3}
              />
              <p className="wsms-text-xs wsms-text-muted-foreground">
                {__('Variables:')} %subscriber_name%, %subscriber_mobile%, %group_name%, %subscribe_date%
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Appearance */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Palette className="wsms-h-5 wsms-w-5" />
            {__('Form Appearance')}
          </CardTitle>
          <CardDescription>
            {__('Customize the look of your subscription form')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Disable Default Styles')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Remove plugin CSS to use your own form styling.')}
              </p>
            </div>
            <Switch
              checked={disableStyle === '1'}
              onCheckedChange={(checked) => setDisableStyle(checked ? '1' : '')}
              aria-label={__('Disable default styles')}
            />
          </div>
        </CardContent>
      </Card>

      {/* GDPR Compliance */}
      {gdprEnabled && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-5 wsms-w-5" />
              {__('GDPR Settings')}
            </CardTitle>
            <CardDescription>
              {__('Configure privacy consent for newsletter subscriptions')}
            </CardDescription>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            <div className="wsms-space-y-2">
              <Label htmlFor="gdprText">{__('Consent Message')}</Label>
              <Textarea
                id="gdprText"
                value={gdprText}
                onChange={(e) => setGdprText(e.target.value)}
                placeholder={__('I agree to receive SMS notifications and understand that my data will be handled according to the privacy policy.')}
                rows={3}
              />
              <p className="wsms-text-xs wsms-text-muted-foreground">
                {__('Privacy consent text shown to subscribers. Required for GDPR compliance.')}
              </p>
            </div>

            <div className="wsms-space-y-2">
              <Label>{__('Checkbox Default State')}</Label>
              <Select value={gdprCheckbox} onValueChange={setGdprCheckbox}>
                <SelectTrigger aria-label={__('GDPR checkbox default state')}>
                  <SelectValue placeholder={__('Select default state')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="checked">{__('Checked')}</SelectItem>
                  <SelectItem value="unchecked">{__('Unchecked')}</SelectItem>
                </SelectContent>
              </Select>
              <p className="wsms-text-xs wsms-text-muted-foreground">
                {__('Must be unchecked by default for GDPR compliance.')}
              </p>
            </div>
          </CardContent>
        </Card>
      )}

      {!gdprEnabled && (
        <Card className="wsms-border-amber-200 wsms-bg-amber-50 dark:wsms-border-amber-900 dark:wsms-bg-amber-950/30">
          <CardContent className="wsms-p-4">
            <p className="wsms-text-sm wsms-text-muted-foreground">
              {__('To enable GDPR settings for newsletters, first enable "GDPR Compliance Enhancements" in the Phone Configuration page.')}
            </p>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
