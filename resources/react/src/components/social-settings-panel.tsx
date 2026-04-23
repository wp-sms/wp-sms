import { __, sprintf } from '@wordpress/i18n';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerDescription,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { ExternalLink } from 'lucide-react';
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
    description: __('Allow users to sign in with their Google account', 'wp-sms'),
    clientIdLabel: __('Client ID', 'wp-sms'),
    clientIdPlaceholder: '123456789.apps.googleusercontent.com',
    clientIdHelp: __('From Google Cloud Console > APIs & Services > Credentials. Create an OAuth 2.0 Client ID.', 'wp-sms'),
    clientSecretHelp: __('The client secret from the same OAuth 2.0 credential.', 'wp-sms'),
    setupUrl: 'https://console.cloud.google.com/apis/credentials',
    setupLabel: __('Google Cloud Console', 'wp-sms'),
    notes: [
      __('Set the authorized redirect URI to: {callback_url}', 'wp-sms'),
      __('Enable the Google+ API or People API in your project.', 'wp-sms'),
    ],
  },
  github: {
    description: __('Allow users to sign in with their GitHub account', 'wp-sms'),
    clientIdLabel: __('Client ID', 'wp-sms'),
    clientIdPlaceholder: 'Iv1.abc123def456',
    clientIdHelp: __('From GitHub > Settings > Developer settings > OAuth Apps. Create a new OAuth App.', 'wp-sms'),
    clientSecretHelp: __('Generate a client secret in the same OAuth App settings page.', 'wp-sms'),
    setupUrl: 'https://github.com/settings/developers',
    setupLabel: __('GitHub Developer Settings', 'wp-sms'),
    notes: [
      __('Set the Authorization callback URL to: {callback_url}', 'wp-sms'),
      __('The Homepage URL should be your WordPress site URL.', 'wp-sms'),
      __('GitHub users with private emails will still be matched — the plugin fetches verified emails via the API.', 'wp-sms'),
    ],
  },
  telegram: {
    description: __('Allow users to sign in with their Telegram account via OpenID Connect', 'wp-sms'),
    clientIdLabel: __('Client ID (Bot ID)', 'wp-sms'),
    clientIdPlaceholder: '123456789',
    clientIdHelp: __('Open @BotFather on Telegram > Bot Settings > Web Login. Your Client ID (Bot ID) is displayed there.', 'wp-sms'),
    clientSecretHelp: __('The Client Secret shown in the same BotFather Web Login section. Keep this secret.', 'wp-sms'),
    setupUrl: 'https://t.me/botfather',
    setupLabel: __('Open @BotFather', 'wp-sms'),
    notes: [
      __('In BotFather > Bot Settings > Web Login, add your site origin and the callback URL: {callback_url}', 'wp-sms'),
      __('Telegram provides the user\'s phone number (not email). Users with matching phone numbers will be auto-linked.', 'wp-sms'),
      __('Request the "telegram:bot_access" scope to allow sending MFA codes via the bot.', 'wp-sms'),
    ],
  },
  line: {
    description: __('Allow users to sign in with their LINE account via OpenID Connect', 'wp-sms'),
    clientIdLabel: __('Channel ID', 'wp-sms'),
    clientIdPlaceholder: '1234567890',
    clientIdHelp: __('From LINE Developers Console > your LINE Login channel > Basic settings > Channel ID.', 'wp-sms'),
    clientSecretHelp: __('The Channel Secret from the same LINE Login channel settings.', 'wp-sms'),
    setupUrl: 'https://developers.line.biz/console/',
    setupLabel: __('LINE Developers Console', 'wp-sms'),
    notes: [
      __('Create a LINE Login channel (not Messaging API) in LINE Developers Console.', 'wp-sms'),
      __('Set the callback URL to: {callback_url}', 'wp-sms'),
      __('LINE Login provides email (optional) and profile info. No phone number is available via standard LINE Login.', 'wp-sms'),
      __('Users who log in via LINE are auto-enrolled for LINE MFA if a Messaging API bot is configured.', 'wp-sms'),
    ],
  },
};

const DEFAULT_HELP: ProviderHelp = {
  description: __('Configure OAuth credentials for this provider', 'wp-sms'),
  clientIdLabel: __('Client ID', 'wp-sms'),
  clientIdPlaceholder: __('Enter client ID', 'wp-sms'),
  clientIdHelp: __('From your provider\'s developer console or dashboard.', 'wp-sms'),
  clientSecretHelp: __('Keep this secret. Never expose it in frontend code.', 'wp-sms'),
};

interface SocialSettingsPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  providerId: string;
  providerLabel: string;
  callbackUrl: string;
  icon: React.ComponentType<React.SVGProps<SVGSVGElement>>;
  settings: SocialProviderSettings;
  onUpdate: (partial: Partial<SocialProviderSettings>) => void;
}

export function SocialSettingsPanel({
  open,
  onOpenChange,
  providerId,
  providerLabel,
  callbackUrl,
  icon: Icon,
  settings,
  onUpdate,
}: SocialSettingsPanelProps) {
  const help = PROVIDER_HELP[providerId] ?? DEFAULT_HELP;

  function interpolate(text: string) {
    return text.replace('{callback_url}', callbackUrl);
  }

  return (
    <Drawer open={open} onOpenChange={onOpenChange}>
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        <DrawerHeader>
          <DrawerTitle className="flex items-center gap-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
              <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            {sprintf(__('%s Settings', 'wp-sms'), providerLabel)}
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
              {help.setupLabel ?? __('Setup Guide', 'wp-sms')}
            </a>
          )}

          <Field>
            <FieldLabel htmlFor={`${providerId}-client-id`}>{help.clientIdLabel} *</FieldLabel>
            <Input
              id={`${providerId}-client-id`}
              type="text"
              dir="ltr"
              value={settings.client_id ?? ''}
              onChange={(e) => onUpdate({ client_id: e.target.value })}
              placeholder={help.clientIdPlaceholder}
            />
            <FieldDescription>
              {help.clientIdHelp}
            </FieldDescription>
          </Field>

          <Field>
            <FieldLabel htmlFor={`${providerId}-client-secret`}>{__('Client Secret', 'wp-sms')} *</FieldLabel>
            <Input
              id={`${providerId}-client-secret`}
              type="password"
              value={settings.client_secret ?? ''}
              onChange={(e) => onUpdate({ client_secret: e.target.value })}
              placeholder={__('Enter client secret', 'wp-sms')}
            />
            <FieldDescription>
              {help.clientSecretHelp}
            </FieldDescription>
          </Field>

          <Field>
            <FieldLabel>{__('Callback URL', 'wp-sms')}</FieldLabel>
            <Input
              type="text"
              dir="ltr"
              value={callbackUrl}
              readOnly
              className="text-xs font-mono bg-muted cursor-text"
              onClick={(e) => {
                (e.target as HTMLInputElement).select();
                navigator.clipboard?.writeText(callbackUrl);
              }}
            />
            <FieldDescription>
              {sprintf(__('Copy this URL and add it to your %s app configuration as the authorized redirect URI.', 'wp-sms'), providerLabel)}
            </FieldDescription>
          </Field>

          {help.notes && help.notes.length > 0 && (
            <div className="rounded-md border bg-muted/50 p-3 space-y-1.5">
              <div className="text-xs font-medium text-muted-foreground">{__('Setup Notes', 'wp-sms')}</div>
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
