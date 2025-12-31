import React from 'react'
import { Phone, Globe, Shield } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MultiSelect } from '@/components/ui/multi-select'
import { useSetting } from '@/context/SettingsContext'
import { getWpSettings } from '@/lib/utils'

export default function PhoneConfig() {
  const { countries = {} } = getWpSettings()

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
            <Phone className="wsms-h-5 wsms-w-5" />
            Administrator Notifications
          </CardTitle>
          <CardDescription>
            Receives system notifications (new users, comments, errors).
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label htmlFor="adminMobile">Admin Phone Number</Label>
            <Input
              id="adminMobile"
              value={adminMobile}
              onChange={(e) => setAdminMobile(e.target.value)}
              placeholder="+1 555 123 4567"
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Enter the full phone number including country code.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Mobile Field Configuration */}
      <Card>
        <CardHeader>
          <CardTitle>Mobile Field Configuration</CardTitle>
          <CardDescription>
            Choose where to collect mobile numbers from users.
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label>Collect Phone Numbers From</Label>
            <Select value={addMobileField} onValueChange={setAddMobileField}>
              <SelectTrigger>
                <SelectValue placeholder="Select source" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="disable">Don't collect — No mobile field</SelectItem>
                <SelectItem value="add_mobile_field_in_profile">User profile — Add field to WordPress user profiles</SelectItem>
                <SelectItem value="add_mobile_field_in_wc_billing">WooCommerce billing (new field) — Add dedicated mobile field</SelectItem>
                <SelectItem value="use_phone_field_in_wc_billing">WooCommerce billing (existing) — Use existing phone field</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="wsms-space-y-2">
            <Label>Field Requirement</Label>
            <Select value={optionalMobileField} onValueChange={setOptionalMobileField}>
              <SelectTrigger>
                <SelectValue placeholder="Select requirement" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="0">Required — Users must enter a mobile number</SelectItem>
                <SelectItem value="optional">Optional — Users can skip this field</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="placeholder">Placeholder Text</Label>
            <Input
              id="placeholder"
              value={mobilePlaceholder}
              onChange={(e) => setMobilePlaceholder(e.target.value)}
              placeholder="e.g., +1 555 000 0000"
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Example format shown in the empty field.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* International Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Globe className="wsms-h-5 wsms-w-5" />
            International Phone Input
          </CardTitle>
          <CardDescription>
            Show a country flag selector for international phone number formatting.
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">International Phone Input</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Show a country flag selector for international phone number formatting.
              </p>
            </div>
            <Switch
              checked={isInternationalEnabled}
              onCheckedChange={(checked) => setInternationalMobile(checked ? '1' : '')}
            />
          </div>

          {isInternationalEnabled && (
            <>
              <div className="wsms-space-y-2">
                <Label>Limit Countries</Label>
                <MultiSelect
                  options={countries}
                  value={onlyCountries}
                  onValueChange={setOnlyCountries}
                  placeholder="All countries"
                  searchPlaceholder="Search countries..."
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Only show these countries in the dropdown. Leave empty to show all.
                </p>
              </div>

              <div className="wsms-space-y-2">
                <Label>Preferred Countries</Label>
                <MultiSelect
                  options={countries}
                  value={preferredCountries}
                  onValueChange={setPreferredCountries}
                  placeholder="None selected"
                  searchPlaceholder="Search countries..."
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Show these countries at the top of the dropdown for quick access.
                </p>
              </div>
            </>
          )}

          {!isInternationalEnabled && (
            <>
              <div className="wsms-space-y-2">
                <Label>Default Country Code</Label>
                <Select value={countryCode} onValueChange={setCountryCode}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select country code" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">None — Don't add country code</SelectItem>
                    {countries && Object.entries(countries).map(([code, country]) => (
                      <SelectItem key={code} value={code}>
                        {typeof country === 'object' ? country.name : country}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Automatically prepend this country code to all phone numbers.
                </p>
              </div>

              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <div className="wsms-space-y-2">
                  <Label htmlFor="minLength">Minimum Digits</Label>
                  <Input
                    id="minLength"
                    type="number"
                    value={minLength}
                    onChange={(e) => setMinLength(e.target.value)}
                    placeholder="10"
                  />
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    Minimum number of digits required (excluding country code).
                  </p>
                </div>
                <div className="wsms-space-y-2">
                  <Label htmlFor="maxLength">Maximum Digits</Label>
                  <Input
                    id="maxLength"
                    type="number"
                    value={maxLength}
                    onChange={(e) => setMaxLength(e.target.value)}
                    placeholder="15"
                  />
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    Maximum number of digits allowed (excluding country code).
                  </p>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* GDPR Compliance */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Shield className="wsms-h-5 wsms-w-5" />
            Data Protection
          </CardTitle>
          <CardDescription>
            Privacy and GDPR compliance settings.
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">GDPR Compliance</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Enable data export/deletion by mobile number and add SMS consent checkbox to forms.
              </p>
            </div>
            <Switch
              checked={gdprCompliance === '1'}
              onCheckedChange={(checked) => setGdprCompliance(checked ? '1' : '')}
            />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
