import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from "../../components/ui/button"
import { Input } from "../../components/ui/input"
import { Label } from "../../components/ui/label"
import { Switch } from "../../components/ui/switch"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "../../components/ui/select"
import { Textarea } from "../../components/ui/textarea"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../components/ui/card"
import { Separator } from "../../components/ui/separator"

export function GeneralSettings() {
  const [isEnabled, setIsEnabled] = useState(true)
  const [enableLogs, setEnableLogs] = useState(false)

  return (
    <div className="space-y-6">
      {/* Plugin Status */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <Label htmlFor="plugin-enabled">{__('Enable SMS Plugin', 'wp-sms')}</Label>
            <p className="text-sm text-muted-foreground">
              {__('Master switch to enable/disable SMS functionality', 'wp-sms')}
            </p>
          </div>
          <Switch
            id="plugin-enabled"
            checked={isEnabled}
            onCheckedChange={setIsEnabled}
          />
        </div>
      </div>

      <Separator />

      {/* Sender Settings */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium">{__('Sender Information', 'wp-sms')}</h3>
        
        <div className="grid gap-4">
          <div className="grid gap-2">
            <Label htmlFor="sender-name">{__('Default Sender Name', 'wp-sms')}</Label>
            <Input
              id="sender-name"
              placeholder={__('Your Site Name', 'wp-sms')}
              defaultValue="WordPress Site"
            />
            <p className="text-xs text-muted-foreground">
              {__('This name will appear as the sender of SMS messages', 'wp-sms')}
            </p>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="sender-number">{__('Default Sender Number', 'wp-sms')}</Label>
            <Input
              id="sender-number"
              placeholder="+1234567890"
              type="tel"
            />
            <p className="text-xs text-muted-foreground">
              {__('Default phone number for sending SMS (if supported by gateway)', 'wp-sms')}
            </p>
          </div>
        </div>
      </div>

      <Separator />

      {/* Message Settings */}
      <div className="space-y-4">
        <h3 className="text-lg font-medium">{__('Message Settings', 'wp-sms')}</h3>
        
        <div className="grid gap-4">
          <div className="grid gap-2">
            <Label htmlFor="message-prefix">{__('Message Prefix', 'wp-sms')}</Label>
            <Input
              id="message-prefix"
              placeholder={__('[Site Name]', 'wp-sms')}
              defaultValue=""
            />
            <p className="text-xs text-muted-foreground">
              {__('Text to prepend to all SMS messages', 'wp-sms')}
            </p>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="message-suffix">{__('Message Suffix', 'wp-sms')}</Label>
            <Input
              id="message-suffix"
              placeholder={__('Reply STOP to opt-out', 'wp-sms')}
              defaultValue=""
            />
            <p className="text-xs text-muted-foreground">
              {__('Text to append to all SMS messages', 'wp-sms')}
            </p>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="character-limit">{__('Character Limit', 'wp-sms')}</Label>
            <Select defaultValue="160">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="160">{__('160 characters (single SMS)', 'wp-sms')}</SelectItem>
                <SelectItem value="320">{__('320 characters (2 SMS)', 'wp-sms')}</SelectItem>
                <SelectItem value="480">{__('480 characters (3 SMS)', 'wp-sms')}</SelectItem>
                <SelectItem value="unlimited">{__('Unlimited', 'wp-sms')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>

      <Separator />

      {/* Logging */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <Label htmlFor="enable-logs">{__('Enable Logging', 'wp-sms')}</Label>
            <p className="text-sm text-muted-foreground">
              {__('Log SMS sending activity for debugging and tracking', 'wp-sms')}
            </p>
          </div>
          <Switch
            id="enable-logs"
            checked={enableLogs}
            onCheckedChange={setEnableLogs}
          />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="log-retention">{__('Log Retention Period', 'wp-sms')}</Label>
          <Select defaultValue="30">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="7">{__('7 days', 'wp-sms')}</SelectItem>
              <SelectItem value="30">{__('30 days', 'wp-sms')}</SelectItem>
              <SelectItem value="90">{__('90 days', 'wp-sms')}</SelectItem>
              <SelectItem value="365">{__('1 year', 'wp-sms')}</SelectItem>
              <SelectItem value="forever">{__('Forever', 'wp-sms')}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit">{__('Save Changes', 'wp-sms')}</Button>
      </div>
    </div>
  )
}