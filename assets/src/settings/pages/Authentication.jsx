import React from 'react'
import { Shield, Key, Smartphone, Clock, Diamond } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { InputField, SettingRow } from '@/components/ui/form-field'
import { useSettings } from '@/context/SettingsContext'

export default function Authentication() {
  const { isAddonActive } = useSettings()
  const hasPro = isAddonActive('pro')
  const hasOtp = isAddonActive('otp')

  if (!hasPro && !hasOtp) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-5 wsms-w-5" />
              Authentication & OTP
              <Badge variant="warning">
                <Diamond className="wsms-mr-1 wsms-h-3 wsms-w-3" />
                Add-on Required
              </Badge>
            </CardTitle>
            <CardDescription>
              Secure your users with SMS-based authentication
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-8 wsms-text-center">
              <Shield className="wsms-mx-auto wsms-h-12 wsms-w-12 wsms-text-muted-foreground" />
              <h3 className="wsms-mt-4 wsms-text-lg wsms-font-semibold">
                Pro or OTP Add-on Required
              </h3>
              <p className="wsms-mt-2 wsms-text-sm wsms-text-muted-foreground">
                SMS authentication features require the Pro pack or OTP-MFA add-on.
                Protect user accounts with one-time passwords and two-factor authentication.
              </p>
              <div className="wsms-mt-6 wsms-flex wsms-justify-center wsms-gap-4">
                <Button>
                  Get Pro Pack
                </Button>
                <Button variant="outline">
                  Learn More
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6">
      {/* OTP Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Key className="wsms-h-5 wsms-w-5" />
            OTP Settings
          </CardTitle>
          <CardDescription>
            Configure one-time password settings
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-grid wsms-grid-cols-1 wsms-gap-4 md:wsms-grid-cols-2">
            <InputField
              label="OTP Length"
              type="number"
              placeholder="6"
              defaultValue="6"
              description="Number of digits in the OTP code"
            />

            <InputField
              label="Expiry Time (seconds)"
              type="number"
              placeholder="300"
              defaultValue="300"
              description="How long until the OTP expires"
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-text-[13px] wsms-font-medium">Resend Cooldown</p>
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Prevent spam by limiting OTP resend frequency
              </p>
            </div>
            <Input type="number" className="wsms-w-24" placeholder="60" defaultValue="60" />
          </div>
        </CardContent>
      </Card>

      {/* Phone Verification */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Smartphone className="wsms-h-5 wsms-w-5" />
            Phone Verification
          </CardTitle>
          <CardDescription>
            Verify user phone numbers during registration
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title="Enable Phone Verification"
            description="Require phone verification during user registration"
          />

          <SettingRow
            title="Verify on Login"
            description="Require OTP verification when users log in"
          />
        </CardContent>
      </Card>

      {/* 2FA Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Shield className="wsms-h-5 wsms-w-5" />
            Two-Factor Authentication
          </CardTitle>
          <CardDescription>
            Add an extra layer of security with SMS-based 2FA
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title="Enable 2FA"
            description="Allow users to enable SMS-based two-factor authentication"
          />

          <SettingRow
            title="Force 2FA for Admins"
            description="Require 2FA for all administrator accounts"
          />
        </CardContent>
      </Card>
    </div>
  )
}
