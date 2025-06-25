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
import { Alert, AlertDescription } from "../../components/ui/alert"
import { Badge } from "../../components/ui/badge"
import { Code, Database, Shield, Zap, AlertTriangle } from "lucide-react"

export function AdvancedSettings() {
  const [enableWebhooks, setEnableWebhooks] = useState(false)
  const [enableQueue, setEnableQueue] = useState(true)
  const [enableCache, setEnableCache] = useState(true)
  const [debugMode, setDebugMode] = useState(false)

  return (
    <div className="space-y-6">
      {/* API & Webhooks */}
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <Code className="w-5 h-5" />
          <h3 className="text-lg font-medium">{__('API & Webhooks', 'wp-sms')}</h3>
        </div>
        
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="enable-webhooks">{__('Enable Webhooks', 'wp-sms')}</Label>
              <p className="text-sm text-muted-foreground">
                {__('Allow external services to receive SMS delivery notifications', 'wp-sms')}
              </p>
            </div>
            <Switch
              id="enable-webhooks"
              checked={enableWebhooks}
              onCheckedChange={setEnableWebhooks}
            />
          </div>

          {enableWebhooks && (
            <div className="ml-6 space-y-4 border-l-2 border-muted pl-4">
              <div className="grid gap-2">
                <Label htmlFor="webhook-url">{__('Webhook URL', 'wp-sms')}</Label>
                <Input
                  id="webhook-url"
                  placeholder="https://yoursite.com/sms-webhook"
                  type="url"
                />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="webhook-secret">{__('Webhook Secret', 'wp-sms')}</Label>
                <Input
                  id="webhook-secret"
                  placeholder={__('Enter a secure secret key', 'wp-sms')}
                  type="password"
                />
                <p className="text-xs text-muted-foreground">
                  {__('Used to verify webhook authenticity', 'wp-sms')}
                </p>
              </div>
            </div>
          )}

          <div className="grid gap-2">
            <Label htmlFor="api-key">{__('API Key', 'wp-sms')}</Label>
            <div className="flex gap-2">
              <Input
                id="api-key"
                value="wps_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                readOnly
                type="password"
              />
              <Button variant="outline">{__('Regenerate', 'wp-sms')}</Button>
            </div>
            <p className="text-xs text-muted-foreground">
              {__('Use this key for API authentication', 'wp-sms')}
            </p>
          </div>
        </div>
      </div>

      <Separator />

      {/* Performance */}
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <Zap className="w-5 h-5" />
          <h3 className="text-lg font-medium">{__('Performance', 'wp-sms')}</h3>
        </div>
        
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="enable-queue">{__('Enable Message Queue', 'wp-sms')}</Label>
              <p className="text-sm text-muted-foreground">
                {__('Queue SMS messages for batch processing and better reliability', 'wp-sms')}
              </p>
            </div>
            <Switch
              id="enable-queue"
              checked={enableQueue}
              onCheckedChange={setEnableQueue}
            />
          </div>

          {enableQueue && (
            <div className="ml-6 space-y-4 border-l-2 border-muted pl-4">
              <div className="grid gap-2">
                <Label htmlFor="queue-batch-size">{__('Batch Size', 'wp-sms')}</Label>
                <Select defaultValue="50">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="10">{__('10 messages', 'wp-sms')}</SelectItem>
                    <SelectItem value="25">{__('25 messages', 'wp-sms')}</SelectItem>
                    <SelectItem value="50">{__('50 messages', 'wp-sms')}</SelectItem>
                    <SelectItem value="100">{__('100 messages', 'wp-sms')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="grid gap-2">
                <Label htmlFor="queue-interval">{__('Processing Interval', 'wp-sms')}</Label>
                <Select defaultValue="60">
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="30">{__('Every 30 seconds', 'wp-sms')}</SelectItem>
                    <SelectItem value="60">{__('Every minute', 'wp-sms')}</SelectItem>
                    <SelectItem value="300">{__('Every 5 minutes', 'wp-sms')}</SelectItem>
                    <SelectItem value="600">{__('Every 10 minutes', 'wp-sms')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          )}

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="enable-cache">{__('Enable Caching', 'wp-sms')}</Label>
              <p className="text-sm text-muted-foreground">
                {__('Cache gateway responses to improve performance', 'wp-sms')}
              </p>
            </div>
            <Switch
              id="enable-cache"
              checked={enableCache}
              onCheckedChange={setEnableCache}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="rate-limit">{__('Rate Limit (per minute)', 'wp-sms')}</Label>
            <Input
              id="rate-limit"
              type="number"
              placeholder="60"
              defaultValue="60"
            />
            <p className="text-xs text-muted-foreground">
              {__('Maximum SMS messages per minute', 'wp-sms')}
            </p>
          </div>
        </div>
      </div>

      <Separator />

      {/* Database */}
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <Database className="w-5 h-5" />
          <h3 className="text-lg font-medium">{__('Database', 'wp-sms')}</h3>
        </div>
        
        <div className="space-y-4">
          <div className="grid gap-2">
            <Label htmlFor="table-prefix">{__('Custom Table Prefix', 'wp-sms')}</Label>
            <Input
              id="table-prefix"
              placeholder="wp_sms_"
              defaultValue="wp_sms_"
            />
            <p className="text-xs text-muted-foreground">
              {__('Prefix for SMS plugin database tables', 'wp-sms')}
            </p>
          </div>

          <div className="flex gap-2">
            <Button variant="outline">
              <Database className="w-4 h-4 mr-2" />
              {__('Export Data', 'wp-sms')}
            </Button>
            <Button variant="outline">
              <Database className="w-4 h-4 mr-2" />
              {__('Import Data', 'wp-sms')}
            </Button>
          </div>
        </div>
      </div>

      <Separator />

      {/* Debug & Development */}
      <div className="space-y-4">
        <div className="flex items-center gap-2">
          <Shield className="w-5 h-5" />
          <h3 className="text-lg font-medium">{__('Debug & Development', 'wp-sms')}</h3>
        </div>
        
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="debug-mode">{__('Debug Mode', 'wp-sms')}</Label>
              <p className="text-sm text-muted-foreground">
                {__('Enable detailed logging for troubleshooting (not recommended for production)', 'wp-sms')}
              </p>
            </div>
            <Switch
              id="debug-mode"
              checked={debugMode}
              onCheckedChange={setDebugMode}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="log-level">{__('Log Level', 'wp-sms')}</Label>
            <Select defaultValue="error">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="debug">Debug (All)</SelectItem>
                <SelectItem value="info">Info</SelectItem>
                <SelectItem value="warning">Warning</SelectItem>
                <SelectItem value="error">Error Only</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="custom-css">{__('Custom CSS', 'wp-sms')}</Label>
            <Textarea
              id="custom-css"
              placeholder="/* Add your custom CSS here */"
              className="font-mono text-sm"
              rows={4}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="custom-js">{__('Custom JavaScript', 'wp-sms')}</Label>
            <Textarea
              id="custom-js"
              placeholder="// Add your custom JavaScript here"
              className="font-mono text-sm"
              rows={4}
            />
          </div>

          {debugMode && (
            <Alert>
              <AlertTriangle className="h-4 w-4" />
              <AlertDescription>
                {__('Debug mode is enabled. This will log sensitive information and should only be used for development.', 'wp-sms')}
              </AlertDescription>
            </Alert>
          )}
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit">{__('Save Advanced Settings', 'wp-sms')}</Button>
      </div>
    </div>
  )
}