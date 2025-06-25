import { useState } from "react"
import { Button } from "../../components/ui/button"
import { Input } from "../../components/ui/input"
import { Label } from "../../components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "../../components/ui/select"
import { Textarea } from "../../components/ui/textarea"
import { Switch } from "../../components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../components/ui/card"
import { Separator } from "../../components/ui/separator"
import { Alert, AlertDescription } from "../../components/ui/alert"
import { Badge } from "../../components/ui/badge"
import { CheckCircle, AlertCircle, Wifi } from "lucide-react"

// Use WordPress i18n directly
const { __ } = (window as any).wp.i18n;

export function GatewaySettings() {
  const [selectedGateway, setSelectedGateway] = useState("twilio")
  const [testMode, setTestMode] = useState(false)
  const [connectionStatus, setConnectionStatus] = useState<"connected" | "disconnected" | "testing">("disconnected")

  const gateways = [
    { value: "twilio", name: "Twilio", status: "active" },
    { value: "aws-sns", name: "AWS SNS", status: "active" },
    { value: "messagebird", name: "MessageBird", status: "active" },
    { value: "nexmo", name: "Vonage (Nexmo)", status: "active" },
    { value: "clickatell", name: "Clickatell", status: "pro" },
    { value: "custom", name: "Custom Gateway", status: "pro" },
  ]

  const handleTestConnection = () => {
    setConnectionStatus("testing")
    // Simulate testing
    setTimeout(() => {
      setConnectionStatus("connected")
    }, 2000)
  }

  return (
    <div className="space-y-6">
      {/* Gateway Selection */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium">{__('SMS Gateway Provider', 'wp-sms')}</h3>
        
        <div className="grid gap-2">
          <Label htmlFor="gateway-provider">{__('Select Gateway', 'wp-sms')}</Label>
          <Select value={selectedGateway} onValueChange={setSelectedGateway}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {gateways.map((gateway) => (
                <SelectItem key={gateway.value} value={gateway.value}>
                  <div className="flex items-center gap-2">
                    <span>{gateway.name}</span>
                    {gateway.status === "pro" && (
                      <Badge variant="secondary" className="text-xs">PRO</Badge>
                    )}
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <Separator />

      {/* Gateway Configuration */}
      {selectedGateway === "twilio" && (
        <div className="space-y-4">
          <h3 className="text-lg font-medium">{__('Twilio Configuration', 'wp-sms')}</h3>
          
          <div className="grid gap-4">
            <div className="grid gap-2">
              <Label htmlFor="twilio-sid">{__('Account SID', 'wp-sms')}</Label>
              <Input
                id="twilio-sid"
                placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                type="password"
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="twilio-token">{__('Auth Token', 'wp-sms')}</Label>
              <Input
                id="twilio-token"
                placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                type="password"
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="twilio-number">{__('Twilio Phone Number', 'wp-sms')}</Label>
              <Input
                id="twilio-number"
                placeholder="+1234567890"
                type="tel"
              />
              <p className="text-xs text-muted-foreground">
                {__('Your Twilio phone number for sending SMS', 'wp-sms')}
              </p>
            </div>
          </div>
        </div>
      )}

      {selectedGateway === "aws-sns" && (
        <div className="space-y-4">
          <h3 className="text-lg font-medium">{__('AWS SNS Configuration', 'wp-sms')}</h3>
          
          <div className="grid gap-4">
            <div className="grid gap-2">
              <Label htmlFor="aws-access-key">{__('Access Key ID', 'wp-sms')}</Label>
              <Input
                id="aws-access-key"
                placeholder="AKIAxxxxxxxxxxxxxxxx"
                type="password"
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="aws-secret-key">{__('Secret Access Key', 'wp-sms')}</Label>
              <Input
                id="aws-secret-key"
                placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                type="password"
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="aws-region">{__('AWS Region', 'wp-sms')}</Label>
              <Select defaultValue="us-east-1">
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="us-east-1">{__('US East (N. Virginia)', 'wp-sms')}</SelectItem>
                  <SelectItem value="us-west-2">{__('US West (Oregon)', 'wp-sms')}</SelectItem>
                  <SelectItem value="eu-west-1">{__('Europe (Ireland)', 'wp-sms')}</SelectItem>
                  <SelectItem value="ap-southeast-1">{__('Asia Pacific (Singapore)', 'wp-sms')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
      )}

      <Separator />

      {/* Connection Testing */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium">{__('Connection Testing', 'wp-sms')}</h3>
        
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <Label htmlFor="test-mode">{__('Test Mode', 'wp-sms')}</Label>
            <p className="text-sm text-muted-foreground">
              {__('Enable test mode to simulate SMS sending without actual delivery', 'wp-sms')}
            </p>
          </div>
          <Switch
            id="test-mode"
            checked={testMode}
            onCheckedChange={setTestMode}
          />
        </div>

        <div className="flex items-center gap-4">
          <Button onClick={handleTestConnection} disabled={connectionStatus === "testing"}>
            <Wifi className="w-4 h-4 mr-2" />
            {connectionStatus === "testing" ? __("Testing...", 'wp-sms') : __("Test Connection", 'wp-sms')}
          </Button>
          
          {connectionStatus === "connected" && (
            <div className="flex items-center gap-2 text-green-600">
              <CheckCircle className="w-4 h-4" />
              <span className="text-sm">{__('Connection successful', 'wp-sms')}</span>
            </div>
          )}
          
          {connectionStatus === "disconnected" && (
            <div className="flex items-center gap-2 text-red-600">
              <AlertCircle className="w-4 h-4" />
              <span className="text-sm">{__('Not connected', 'wp-sms')}</span>
            </div>
          )}
        </div>

        <div className="grid gap-2">
          <Label htmlFor="test-number">{__('Test Phone Number', 'wp-sms')}</Label>
          <Input
            id="test-number"
            placeholder="+1234567890"
            type="tel"
          />
          <Button variant="outline" className="w-fit">
            {__('Send Test SMS', 'wp-sms')}
          </Button>
        </div>
      </div>

      {testMode && (
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            {__('Test mode is enabled. SMS messages will be logged but not actually sent.', 'wp-sms')}
          </AlertDescription>
        </Alert>
      )}

      <div className="flex justify-end">
        <Button type="submit">{__('Save Gateway Settings', 'wp-sms')}</Button>
      </div>
    </div>
  )
}