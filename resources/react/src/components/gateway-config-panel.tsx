import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useRef } from 'react';
import { useConfirm } from '@/components/confirm-provider';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerFooter,
  DrawerTitle,
  DrawerDescription,
} from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';
import { Field, FieldLabel } from '@/components/ui/field';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ExternalLink, Send, Loader2, ChevronDown, CheckCircle2, CircleAlert, Plug } from 'lucide-react';
import { toast } from 'sonner';
import { GatewayConfigForm, channelLabel, ensureConfig } from '@/components/gateway-config-form';
import type { Gateway, GatewayConfig, GatewayTestResult, TestConnectionResult } from '@/lib/api';

interface GatewayConfigPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  gateway: Gateway | null;
  onSave: (config: Record<string, Record<string, unknown>>) => Promise<void>;
  onTest: (id: string, data: { channel?: string; to: string; body?: string }) => Promise<GatewayTestResult>;
  onTestConnection?: (id: string) => Promise<TestConnectionResult>;
}

export function GatewayConfigPanel({
  open,
  onOpenChange,
  gateway,
  onSave,
  onTest,
  onTestConnection,
}: GatewayConfigPanelProps) {
  const confirm = useConfirm();
  const [draftConfig, setDraftConfig] = useState<GatewayConfig>({ shared: {}, channels: {}, is_default: {} });
  const initialConfigRef = useRef<string>('');
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testChannel, setTestChannel] = useState('');
  const [testTo, setTestTo] = useState('');
  const [testBody, setTestBody] = useState('');
  const [testResult, setTestResult] = useState<GatewayTestResult | null>(null);
  const [connectionTesting, setConnectionTesting] = useState(false);
  const [connectionResult, setConnectionResult] = useState<TestConnectionResult | null>(null);

  // Reset state when panel opens with a new gateway
  useEffect(() => {
    if (open && gateway) {
      const cfg = ensureConfig(gateway.config);
      setDraftConfig(cfg);
      initialConfigRef.current = JSON.stringify(cfg);
      setTestChannel(gateway.supported_channels[0] ?? '');
      setTestTo('');
      setTestBody('');
      setTestResult(null);
      setConnectionResult(null);
    }
  }, [open, gateway]);

  // Clear stale connection result when config changes
  useEffect(() => { setConnectionResult(null); }, [draftConfig]);

  if (!gateway) return null;

  const setupUrl = gateway.metadata.setup_url ?? gateway.metadata.website;
  const setupNotes = gateway.metadata.setup_notes;
  const setupLabel = gateway.metadata.setup_url
    ? sprintf(__('Open %s Dashboard', 'wp-sms'), gateway.name)
    : __('Visit website', 'wp-sms');

  async function handleSave() {
    if (!gateway) return;
    setSaving(true);
    try {
      await onSave({ [gateway.id]: draftConfig as unknown as Record<string, unknown> });
      toast.success(__('Gateway configuration saved', 'wp-sms'));
      onOpenChange(false);
    } catch {
      toast.error(__('Failed to save configuration', 'wp-sms'));
    } finally {
      setSaving(false);
    }
  }

  async function handleTest() {
    if (!gateway) return;
    setTesting(true);
    setTestResult(null);
    try {
      const result = await onTest(gateway.id, {
        channel: gateway.supported_channels.length > 1 ? testChannel : undefined,
        to: testTo,
        body: testBody || undefined,
      });
      setTestResult(result);
    } catch {
      setTestResult({ success: false, data: { status: 'error', provider_id: null, error: 'Request failed' } });
    } finally {
      setTesting(false);
    }
  }

  async function handleTestConnection() {
    if (!gateway || !onTestConnection) return;
    setConnectionTesting(true);
    setConnectionResult(null);
    try {
      const result = await onTestConnection(gateway.id);
      setConnectionResult(result);
    } catch {
      setConnectionResult({ success: false, message: 'Request failed', details: {} });
    } finally {
      setConnectionTesting(false);
    }
  }

  const recipientPlaceholder = testChannel === 'email' ? 'user@example.com' : '+1234567890';

  return (
    <Drawer open={open} onOpenChange={async (o) => {
      if (!o && JSON.stringify(draftConfig) !== initialConfigRef.current) {
        const ok = await confirm({
          title: __('Discard changes?', 'wp-sms'),
          description: __('You have unsaved configuration changes that will be lost.', 'wp-sms'),
          confirmLabel: __('Discard', 'wp-sms'),
          variant: 'destructive',
        });
        if (!ok) return;
      }
      onOpenChange(o);
    }}>
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle>{gateway.name}</DrawerTitle>
          {gateway.metadata.description && (
            <DrawerDescription>{gateway.metadata.description}</DrawerDescription>
          )}
          {setupUrl && (
            <a
              href={setupUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 text-sm text-primary hover:underline mt-1"
            >
              <ExternalLink className="h-3.5 w-3.5" />
              {setupLabel}
            </a>
          )}
        </DrawerHeader>

        <div className="flex-1 space-y-6 px-4 pb-4">
          {/* Setup Notes */}
          {setupNotes && setupNotes.length > 0 && (
            <div className="rounded-md border bg-muted/50 p-3 space-y-1.5">
              <div className="text-xs font-medium text-muted-foreground">{__('Setup Guide', 'wp-sms')}</div>
              <ul className="list-disc list-inside space-y-1">
                {setupNotes.map((note, i) => (
                  <li key={i} className="text-xs text-muted-foreground">{note}</li>
                ))}
              </ul>
            </div>
          )}

          <GatewayConfigForm
            gatewayId={gateway.id}
            schema={gateway.config_schema}
            values={draftConfig}
            supportedChannels={gateway.supported_channels}
            onChange={setDraftConfig}
          />

          <Separator />

          {/* Test Connection */}
          {onTestConnection && gateway.features?.test_connection && (
            <div className="space-y-3">
              <div className="flex items-center gap-3">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleTestConnection}
                  disabled={!gateway.is_configured || connectionTesting}
                >
                  {connectionTesting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plug className="h-4 w-4" />}
                  {__('Test Connection', 'wp-sms')}
                </Button>
                {!gateway.is_configured && (
                  <span className="text-xs text-muted-foreground">{__('Save configuration to enable connection testing', 'wp-sms')}</span>
                )}
              </div>

              {connectionResult && (
                connectionResult.success ? (
                  <Alert variant="success">
                    <CheckCircle2 className="h-4 w-4" />
                    <AlertTitle>{connectionResult.message}</AlertTitle>
                    {Object.keys(connectionResult.details).length > 0 && (
                      <AlertDescription>
                        {Object.entries(connectionResult.details).map(([k, v]) => `${k}: ${v}`).join(' · ')}
                      </AlertDescription>
                    )}
                  </Alert>
                ) : (
                  <Alert variant="destructive">
                    <CircleAlert className="h-4 w-4" />
                    <AlertTitle>{__('Connection failed', 'wp-sms')}</AlertTitle>
                    <AlertDescription>{connectionResult.message}</AlertDescription>
                  </Alert>
                )
              )}
            </div>
          )}

          {/* Test Send — collapsible, visually distinct */}
          <Collapsible>
            <div className="rounded-md border bg-muted/30 p-3">
              <CollapsibleTrigger
                className="group flex items-center justify-between w-full text-sm font-medium disabled:opacity-50"
                disabled={!gateway.is_configured}
              >
                <span className="flex items-center gap-2">
                  <Send className="h-4 w-4" />
                  {__('Test Send', 'wp-sms')}
                </span>
                <ChevronDown className="h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
              </CollapsibleTrigger>

              <CollapsibleContent className="pt-4 space-y-4">
                <p className="text-xs text-muted-foreground">
                  {__('Verify your configuration by sending a test message. This will send a real message.', 'wp-sms')}
                </p>

                {gateway.supported_channels.length > 1 && (
                  <Field>
                    <FieldLabel htmlFor="test-channel">{__('Channel', 'wp-sms')}</FieldLabel>
                    <Select value={testChannel} onValueChange={setTestChannel}>
                      <SelectTrigger id="test-channel">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {gateway.supported_channels.map((ch) => (
                          <SelectItem key={ch} value={ch}>{channelLabel(ch)}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                )}

                <Field>
                  <FieldLabel htmlFor="test-to">{__('Recipient', 'wp-sms')}</FieldLabel>
                  <Input
                    id="test-to"
                    type="text"
                    dir="ltr"
                    value={testTo}
                    onChange={(e) => setTestTo(e.target.value)}
                    placeholder={recipientPlaceholder}
                  />
                </Field>

                <Field>
                  <FieldLabel htmlFor="test-body">{__('Message', 'wp-sms')}</FieldLabel>
                  <Textarea
                    id="test-body"
                    value={testBody}
                    onChange={(e) => setTestBody(e.target.value)}
                    placeholder={__('Hello from WSMS!', 'wp-sms')}
                    rows={2}
                  />
                </Field>

                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleTest}
                  disabled={!testTo || testing}
                >
                  {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                  {__('Send Test', 'wp-sms')}
                </Button>

                {testResult && (
                  testResult.success ? (
                    <Alert variant="success">
                      <CheckCircle2 className="h-4 w-4" />
                      <AlertTitle>{__('Sent successfully', 'wp-sms')}</AlertTitle>
                      {testResult.data.provider_id && (
                        <AlertDescription>{__('Provider ID:', 'wp-sms')} {testResult.data.provider_id}</AlertDescription>
                      )}
                    </Alert>
                  ) : (
                    <Alert variant="destructive">
                      <CircleAlert className="h-4 w-4" />
                      <AlertTitle>{__('Test failed', 'wp-sms')}</AlertTitle>
                      <AlertDescription>{testResult.data.error ?? 'An unknown error occurred'}</AlertDescription>
                    </Alert>
                  )
                )}
              </CollapsibleContent>
            </div>

            {!gateway.is_configured && (
              <p className="text-xs text-muted-foreground mt-2">{__('Save configuration to enable test sending.', 'wp-sms')}</p>
            )}
          </Collapsible>
        </div>

        <DrawerFooter>
          <Button onClick={handleSave} disabled={saving}>
            {saving && <Loader2 className="h-4 w-4 animate-spin" />}
            {__('Save', 'wp-sms')}
          </Button>
        </DrawerFooter>
      </DrawerContent>
    </Drawer>
  );
}
