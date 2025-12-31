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
            Mobile number where the administrator will receive notifications
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label htmlFor="adminMobile">Admin Mobile Number</Label>
            <Input
              id="adminMobile"
              value={adminMobile}
              onChange={(e) => setAdminMobile(e.target.value)}
              placeholder="+1234567890"
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
            Configure how user mobile numbers are collected and stored
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label>Mobile Number Field Source</Label>
            <Select value={addMobileField} onValueChange={setAddMobileField}>
              <SelectTrigger>
                <SelectValue placeholder="Select source" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="disable">Disable</SelectItem>
                <SelectItem value="add_mobile_field_in_profile">Insert a mobile number field into user profiles</SelectItem>
                <SelectItem value="add_mobile_field_in_wc_billing">Add a mobile number field to WooCommerce billing</SelectItem>
                <SelectItem value="use_phone_field_in_wc_billing">Use the existing WooCommerce billing phone field</SelectItem>
              </SelectContent>
            </Select>
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Create a new mobile number field or use an existing phone field.
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label>Mobile Field Mandatory Status</Label>
            <Select value={optionalMobileField} onValueChange={setOptionalMobileField}>
              <SelectTrigger>
                <SelectValue placeholder="Select status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="0">Required</SelectItem>
                <SelectItem value="optional">Optional</SelectItem>
              </SelectContent>
            </Select>
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Set the mobile number field as optional or required.
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="placeholder">Mobile Field Placeholder</Label>
            <Input
              id="placeholder"
              value={mobilePlaceholder}
              onChange={(e) => setMobilePlaceholder(e.target.value)}
              placeholder="e.g., +1234567890"
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Enter a sample format for the mobile number that users will see.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* International Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Globe className="wsms-h-5 wsms-w-5" />
            International Number Input
          </CardTitle>
          <CardDescription>
            Configure international phone number input settings
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Enable International Input</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Add a flag dropdown for international format support in the mobile number input field.
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
                <Label>Restricted Countries</Label>
                <MultiSelect
                  options={countries}
                  value={onlyCountries}
                  onValueChange={setOnlyCountries}
                  placeholder="All countries (no restriction)"
                  searchPlaceholder="Search countries..."
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Display only the countries you specify in the dropdown. Leave empty to show all countries.
                </p>
              </div>

              <div className="wsms-space-y-2">
                <Label>Preferred Countries</Label>
                <MultiSelect
                  options={countries}
                  value={preferredCountries}
                  onValueChange={setPreferredCountries}
                  placeholder="No preferred countries"
                  searchPlaceholder="Search countries..."
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Specify countries to appear at the top of the dropdown list for quick selection.
                </p>
              </div>
            </>
          )}

          {!isInternationalEnabled && (
            <>
              <div className="wsms-space-y-2">
                <Label>Country Code Prefix</Label>
                <Select value={countryCode} onValueChange={setCountryCode}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select country code" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">No country code (Global / Local)</SelectItem>
                    {countries && Object.entries(countries).map(([code, country]) => (
                      <SelectItem key={code} value={code}>
                        {typeof country === 'object' ? country.name : country}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  If the user's mobile number requires a country code, select it from the list.
                </p>
              </div>

              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <div className="wsms-space-y-2">
                  <Label htmlFor="minLength">Minimum Length</Label>
                  <Input
                    id="minLength"
                    type="number"
                    value={minLength}
                    onChange={(e) => setMinLength(e.target.value)}
                    placeholder="e.g., 10"
                  />
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    Shortest allowed mobile number.
                  </p>
                </div>
                <div className="wsms-space-y-2">
                  <Label htmlFor="maxLength">Maximum Length</Label>
                  <Input
                    id="maxLength"
                    type="number"
                    value={maxLength}
                    onChange={(e) => setMaxLength(e.target.value)}
                    placeholder="e.g., 15"
                  />
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    Longest allowed mobile number.
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
            Data Protection Settings
          </CardTitle>
          <CardDescription>
            Enhance user privacy with GDPR-focused settings
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">GDPR Compliance Enhancements</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Enables user data export and deletion via mobile number and adds a consent checkbox for SMS newsletter subscriptions.
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
