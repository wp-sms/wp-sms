import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerDescription,
} from '@/components/ui/drawer';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { ExternalLink } from 'lucide-react';
import { getConfig } from '@/lib/api';
import type { SocialProviderSettings } from '@/lib/api';

interface ProviderHelp {
  description: string;
  clientIdLabel: string;
  clientIdPlaceholder: string;
  clientIdHelp: string;
  clientSecretHelp: string;
  setupUrl?: string;
  setupLabel?: string;
  notes?: string[];
}

const PROVIDER_HELP: Record<string, ProviderHelp> = {
  google: {
    description: 'Allow users to sign in with their Google account',
    clientIdLabel: 'Client ID',
    clientIdPlaceholder: '123456789.apps.googleusercontent.com',
    clientIdHelp: 'From Google Cloud Console > APIs & Services > Credentials. Create an OAuth 2.0 Client ID.',
    clientSecretHelp: 'The client secret from the same OAuth 2.0 credential.',
    setupUrl: 'https://console.cloud.google.com/apis/credentials',
    setupLabel: 'Google Cloud Console',
    notes: [
      'Set the authorized redirect URI to: {callback_url}',
      'Enable the Google+ API or People API in your project.',
    ],
  },
  telegram: {
    description: 'Allow users to sign in with their Telegram account via OpenID Connect',
    clientIdLabel: 'Client ID (Bot ID)',
    clientIdPlaceholder: '123456789',
    clientIdHelp: 'Open @BotFather on Telegram > Bot Settings > Web Login. Your Client ID (Bot ID) is displayed there.',
    clientSecretHelp: 'The Client Secret shown in the same BotFather Web Login section. Keep this secret.',
    setupUrl: 'https://t.me/botfather',
    setupLabel: 'Open @BotFather',
    notes: [
      'In BotFather > Bot Settings > Web Login, add your site origin and the callback URL: {callback_url}',
      'Telegram provides the user\'s phone number (not email). Users with matching phone numbers will be auto-linked.',
      'Request the "telegram:bot_access" scope to allow sending MFA codes via the bot.',
    ],
  },
};

const DEFAULT_HELP: ProviderHelp = {
  description: 'Configure OAuth credentials for this provider',
  clientIdLabel: 'Client ID',
  clientIdPlaceholder: 'Enter client ID',
  clientIdHelp: 'From your provider\'s developer console or dashboard.',
  clientSecretHelp: 'Keep this secret. Never expose it in frontend code.',
};

interface SocialSettingsSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  providerId: string;
  providerLabel: string;
  icon: React.ComponentType<React.SVGProps<SVGSVGElement>>;
  settings: SocialProviderSettings;
  onUpdate: (partial: Partial<SocialProviderSettings>) => void;
}

export function SocialSettingsSheet({
  open,
  onOpenChange,
  providerId,
  providerLabel,
  icon: Icon,
  settings,
  onUpdate,
}: SocialSettingsSheetProps) {
  const help = PROVIDER_HELP[providerId] ?? DEFAULT_HELP;
  const callbackUrl = `${getConfig().restUrl}auth/social/callback/${providerId}`;

  function interpolate(text: string) {
    return text.replace('{callback_url}', callbackUrl);
  }

  return (
    <Drawer open={open} onOpenChange={onOpenChange} direction="right">
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
              <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            {providerLabel} Settings
          </DrawerTitle>
          <DrawerDescription>
            {help.description}
          </DrawerDescription>
        </DrawerHeader>

        <div className="space-y-6 px-4 pb-4">
          {help.setupUrl && (
            <a
              href={help.setupUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-2 text-sm text-primary hover:underline"
            >
              <ExternalLink className="h-3.5 w-3.5" />
              {help.setupLabel ?? 'Setup Guide'}
            </a>
          )}

          <Field>
            <FieldLabel htmlFor={`${providerId}-client-id`}>{help.clientIdLabel}</FieldLabel>
            <Input
              id={`${providerId}-client-id`}
              type="text"
              value={settings.client_id ?? ''}
              onChange={(e) => onUpdate({ client_id: e.target.value })}
              placeholder={help.clientIdPlaceholder}
            />
            <FieldDescription>
              {help.clientIdHelp}
            </FieldDescription>
          </Field>

          <Field>
            <FieldLabel htmlFor={`${providerId}-client-secret`}>Client Secret</FieldLabel>
            <Input
              id={`${providerId}-client-secret`}
              type="password"
              value={settings.client_secret ?? ''}
              onChange={(e) => onUpdate({ client_secret: e.target.value })}
              placeholder="Enter client secret"
            />
            <FieldDescription>
              {help.clientSecretHelp}
            </FieldDescription>
          </Field>

          {/* Callback URL (read-only) */}
          <Field>
            <FieldLabel>Callback URL</FieldLabel>
            <Input
              type="text"
              value={callbackUrl}
              readOnly
              className="text-xs font-mono bg-muted cursor-text"
              onClick={(e) => {
                (e.target as HTMLInputElement).select();
                navigator.clipboard?.writeText(callbackUrl);
              }}
            />
            <FieldDescription>
              Copy this URL and add it to your {providerLabel} app configuration as the authorized redirect URI.
            </FieldDescription>
          </Field>

          {help.notes && help.notes.length > 0 && (
            <div className="rounded-md border bg-muted/50 p-3 space-y-1.5">
              <div className="text-xs font-medium text-muted-foreground">Setup Notes</div>
              <ul className="list-disc list-inside space-y-1">
                {help.notes.map((note, i) => (
                  <li key={i} className="text-xs text-muted-foreground">{interpolate(note)}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </DrawerContent>
    </Drawer>
  );
}
