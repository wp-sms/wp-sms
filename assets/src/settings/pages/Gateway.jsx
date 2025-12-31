import React, { useState, useMemo } from 'react'
import { Search, CheckCircle, Radio, Send, Loader2 } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { InputField, SelectField, SwitchField } from '@/components/ui/form-field'
import { MultiSelect } from '@/components/ui/multi-select'
import { useSettings, useSetting } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { getWpSettings, cn } from '@/lib/utils'

export default function Gateway() {
  const { testGatewayConnection } = useSettings()
  const { toast } = useToast()
  const { gateways = {}, countries = {} } = getWpSettings()

  const [gatewayName, setGatewayName] = useSetting('gateway_name', '')
  const [gatewayUsername, setGatewayUsername] = useSetting('gateway_username', '')
  const [gatewayPassword, setGatewayPassword] = useSetting('gateway_password', '')
  const [gatewayKey, setGatewayKey] = useSetting('gateway_key', '')
  const [gatewaySenderId, setGatewaySenderId] = useSetting('gateway_sender_id', '')

  const [deliveryMethod, setDeliveryMethod] = useSetting('sms_delivery_method', 'api_direct_send')
  const [sendUnicode, setSendUnicode] = useSetting('send_unicode', '')
  const [cleanNumbers, setCleanNumbers] = useSetting('clean_numbers', '')
  const [localNumbersOnly, setLocalNumbersOnly] = useSetting('send_only_local_numbers', '')
  const [localNumbersCountries, setLocalNumbersCountries] = useSetting('only_local_numbers_countries', [])

  const [creditInMenu, setCreditInMenu] = useSetting('account_credit_in_menu', '')
  const [creditInSendSms, setCreditInSendSms] = useSetting('account_credit_in_sendsms', '')

  const [searchQuery, setSearchQuery] = useState('')
  const [testing, setTesting] = useState(false)

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
    if (!searchQuery) return gatewayList
    const query = searchQuery.toLowerCase()
    return gatewayList.filter(
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
      toast({
        title: result.success ? 'Connection Successful' : 'Connection Failed',
        description: result.success ? `Credit: ${result.credit}` : result.error,
        variant: result.success ? 'success' : 'destructive',
      })
    } catch (error) {
      toast({ title: 'Error', description: error.message, variant: 'destructive' })
    }
    setTesting(false)
  }

  return (
    <div className="wsms-space-y-4">
      {/* Gateway Selection */}
      <Card>
        <CardHeader>
          <CardTitle>SMS Gateway</CardTitle>
          <CardDescription>Select your SMS service provider. Configure credentials below after selecting.</CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-relative">
            <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms-h-4 wsms-w-4 wsms--translate-y-1/2 wsms-text-muted-foreground" />
            <Input
              placeholder="Search gateways..."
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
                      onClick={() => setGatewayName(gateway.key)}
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
                No gateways found
              </div>
            )}
          </div>

          {gatewayName && (
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              Selected: <span className="wsms-font-medium wsms-text-foreground">{gatewayName}</span>
            </p>
          )}
        </CardContent>
      </Card>

      {/* Gateway Credentials */}
      {gatewayName && (
        <Card>
          <CardHeader>
            <CardTitle>Credentials</CardTitle>
            <CardDescription>API credentials for {gatewayName}</CardDescription>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            <div className="wsms-grid wsms-grid-cols-1 wsms-gap-4 md:wsms-grid-cols-2">
              <InputField
                label="API Username"
                value={gatewayUsername}
                onChange={(e) => setGatewayUsername(e.target.value)}
                placeholder="Your gateway account username"
              />
              <InputField
                label="API Password"
                type="password"
                value={gatewayPassword}
                onChange={(e) => setGatewayPassword(e.target.value)}
                placeholder="Your gateway account password"
              />
              <InputField
                label="API Key"
                value={gatewayKey}
                onChange={(e) => setGatewayKey(e.target.value)}
                placeholder="Your gateway API key or token"
              />
              <InputField
                label="Sender ID"
                description="The name recipients see as the sender. Must be approved by your gateway."
                value={gatewaySenderId}
                onChange={(e) => setGatewaySenderId(e.target.value)}
                placeholder="e.g., MyBrand"
              />
            </div>

            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-pt-4 wsms-border-t wsms-border-border">
              <Button size="sm" onClick={handleTestConnection} disabled={testing}>
                {testing ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1 wsms-animate-spin" />
                    Testing...
                  </>
                ) : (
                  <>
                    <Send className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                    Test Connection
                  </>
                )}
              </Button>
              <span className="wsms-text-[12px] wsms-text-muted-foreground">
                Verify credentials
              </span>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Delivery Settings */}
      <Card>
        <CardHeader>
          <CardTitle>Delivery Settings</CardTitle>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SelectField
            label="Delivery Method"
            description="How SMS messages are processed and sent."
            value={deliveryMethod}
            onValueChange={setDeliveryMethod}
            placeholder="Select method"
            options={[
              { value: 'api_direct_send', label: 'Instant — Send immediately when triggered' },
              { value: 'api_async_send', label: 'Background — Process in background (reduces page load time)' },
              { value: 'api_queued_send', label: 'Queue — Add to queue for batch processing' },
            ]}
          />

          <div className="wsms-divide-y wsms-divide-border wsms-border-t wsms-border-border">
            <SwitchField
              label="Enable Unicode"
              description="Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS."
              checked={sendUnicode === '1'}
              onCheckedChange={(checked) => setSendUnicode(checked ? '1' : '')}
            />
            <SwitchField
              label="Auto-format Numbers"
              description="Automatically remove spaces and special characters from phone numbers before sending."
              checked={cleanNumbers === '1'}
              onCheckedChange={(checked) => setCleanNumbers(checked ? '1' : '')}
            />
            <SwitchField
              label="Restrict to Specific Countries"
              description="Only send SMS to phone numbers from selected countries."
              checked={localNumbersOnly === '1'}
              onCheckedChange={(checked) => setLocalNumbersOnly(checked ? '1' : '')}
            />
          </div>

          {localNumbersOnly === '1' && (
            <div className="wsms-space-y-2 wsms-pt-4 wsms-border-t wsms-border-border">
              <Label>Allowed Countries</Label>
              <MultiSelect
                options={countries}
                value={localNumbersCountries}
                onValueChange={setLocalNumbersCountries}
                placeholder="Select countries..."
                searchPlaceholder="Search countries..."
              />
              <p className="wsms-text-xs wsms-text-muted-foreground">
                SMS will only be sent to numbers from these countries.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Credit Display */}
      <Card>
        <CardHeader>
          <CardTitle>Credit Display</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="wsms-divide-y wsms-divide-border">
            <SwitchField
              label="Show Credit in Menu"
              description="Display your SMS credit balance in the WordPress admin menu bar."
              checked={creditInMenu === '1'}
              onCheckedChange={(checked) => setCreditInMenu(checked ? '1' : '')}
            />
            <SwitchField
              label="Show Credit on Send Page"
              description="Display your remaining SMS credits when composing messages."
              checked={creditInSendSms === '1'}
              onCheckedChange={(checked) => setCreditInSendSms(checked ? '1' : '')}
            />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
