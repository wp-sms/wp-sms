import React from 'react'
import { Phone, Smartphone, Globe, Shield } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { InputField, MultiSelectField, SettingRow } from '@/components/ui/form-field'
import { useSetting } from '@/context/SettingsContext'
import { getWpSettings, __ } from '@/lib/utils'

export default function PhoneConfig() {
  const { countriesByCode = {}, countriesByDialCode = {} } = getWpSettings()

  // Admin mobile
  const [adminMobile, setAdminMobile] = useSetting('admin_mobile_number', '')

  // Mobile field configuration
  const [addMobileField, setAddMobileField] = useSetting('add_mobile_field', 'add_mobile_field_in_profile')
  const [optionalMobileField, setOptionalMobileField] = useSetting('optional_mobile_field', '0')
  const [mobilePlaceholder, setMobilePlaceholder] = useSetting('mobile_terms_field_place_holder', '')

  // International settings
  const [internationalMobile, setInternationalMobile] = useSetting('international_mobile', '')
  const [countryCode, setCountryCode] = useSetting('mobile_county_code', '0')
  const [onlyCountries, setOnlyCountries] = useSetting('international_mobile_only_countries', [])
  const [preferredCountries, setPreferredCountries] = useSetting('international_mobile_preferred_countries', [])

  // Length validation (shown when international is disabled)
  const [minLength, setMinLength] = useSetting('mobile_terms_minimum', '')
  const [maxLength, setMaxLength] = useSetting('mobile_terms_maximum', '')

  // GDPR
  const [gdprCompliance, setGdprCompliance] = useSetting('gdpr_compliance', '')

  const isInternationalEnabled = internationalMobile === '1'

  return (
    <div className="wsms-space-y-6">
      {/* Admin Phone */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Phone className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Administrator Notifications')}
          </CardTitle>
          <CardDescription>
            {__('Receives system notifications (new users, comments, errors).')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <InputField
            label={__('Admin Phone Number')}
            value={adminMobile}
            onChange={(e) => setAdminMobile(e.target.value)}
            placeholder="+1 555 123 4567"
            description={__('Enter the full phone number including country code.')}
          />
        </CardContent>
      </Card>

      {/* Mobile Field Configuration */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Smartphone className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Mobile Field Configuration')}
          </CardTitle>
          <CardDescription>
            {__('Choose where to collect mobile numbers from users.')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label>{__('Collect Phone Numbers From')}</Label>
            <Select value={addMobileField} onValueChange={setAddMobileField}>
              <SelectTrigger aria-label={__('Collect phone numbers from')}>
                <SelectValue placeholder={__('Select source')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="disable">{__("Don't collect — No mobile field")}</SelectItem>
                <SelectItem value="add_mobile_field_in_profile">{__('User profile — Add field to WordPress user profiles')}</SelectItem>
                <SelectItem value="add_mobile_field_in_wc_billing">{__('WooCommerce billing (new field) — Add dedicated mobile field')}</SelectItem>
                <SelectItem value="use_phone_field_in_wc_billing">{__('WooCommerce billing (existing) — Use existing phone field')}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {addMobileField !== 'disable' && (
            <>
              <div className="wsms-space-y-2">
                <Label>{__('Field Requirement')}</Label>
                <Select value={optionalMobileField} onValueChange={setOptionalMobileField}>
                  <SelectTrigger aria-label={__('Field requirement')}>
                    <SelectValue placeholder={__('Select requirement')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{__('Required — Users must enter a mobile number')}</SelectItem>
                    <SelectItem value="optional">{__('Optional — Users can skip this field')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <InputField
                label={__('Placeholder Text')}
                value={mobilePlaceholder}
                onChange={(e) => setMobilePlaceholder(e.target.value)}
                placeholder={__('e.g., +1 555 000 0000')}
                description={__('Example format shown in the empty field.')}
              />
            </>
          )}
        </CardContent>
      </Card>

      {/* International Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Globe className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('International Phone Input')}
          </CardTitle>
          <CardDescription>
            {__('Show a country flag selector for international phone number formatting.')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('International Phone Input')}
            description={__('Show a country flag selector for international phone number formatting.')}
            checked={isInternationalEnabled}
            onCheckedChange={(checked) => setInternationalMobile(checked ? '1' : '')}
          />

          {isInternationalEnabled && (
            <>
              <MultiSelectField
                label={__('Limit Countries')}
                options={countriesByCode}
                value={onlyCountries}
                onValueChange={setOnlyCountries}
                placeholder={__('All countries')}
                searchPlaceholder={__('Search countries...')}
                description={__('Only show these countries in the dropdown. Leave empty to show all.')}
              />

              <MultiSelectField
                label={__('Preferred Countries')}
                options={countriesByCode}
                value={preferredCountries}
                onValueChange={setPreferredCountries}
                placeholder={__('None selected')}
                searchPlaceholder={__('Search countries...')}
                description={__('Show these countries at the top of the dropdown for quick access.')}
              />
            </>
          )}

          {!isInternationalEnabled && (
            <>
              <div className="wsms-space-y-2">
                <Label>{__('Default Country Code')}</Label>
                <Select value={countryCode} onValueChange={setCountryCode}>
                  <SelectTrigger aria-label={__('Default country code')}>
                    <SelectValue placeholder={__('Select country code')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{__("None — Don't add country code")}</SelectItem>
                    {countriesByDialCode && Object.entries(countriesByDialCode).map(([dialCode, label]) => (
                      <SelectItem key={dialCode} value={dialCode}>
                        {label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Automatically prepend this country code to all phone numbers.')}
                </p>
              </div>

              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <InputField
                  label={__('Minimum Digits')}
                  type="number"
                  value={minLength}
                  onChange={(e) => setMinLength(e.target.value)}
                  placeholder="10"
                  description={__('Minimum number of digits required (excluding country code).')}
                />
                <InputField
                  label={__('Maximum Digits')}
                  type="number"
                  value={maxLength}
                  onChange={(e) => setMaxLength(e.target.value)}
                  placeholder="15"
                  description={__('Maximum number of digits allowed (excluding country code).')}
                />
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* GDPR Compliance */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Shield className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Data Protection')}
          </CardTitle>
          <CardDescription>
            {__('Privacy and GDPR compliance settings.')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('GDPR Compliance')}
            description={__('Enable data export/deletion by mobile number and add SMS consent checkbox to forms.')}
            checked={gdprCompliance === '1'}
            onCheckedChange={(checked) => setGdprCompliance(checked ? '1' : '')}
          />
        </CardContent>
      </Card>
    </div>
  )
}
