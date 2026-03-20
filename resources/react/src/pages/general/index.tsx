import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Phone } from 'lucide-react';
import { SITE_PHONE_CHANNELS } from '@/lib/constants';
import type { AuthSettings, SitePhoneChannel } from '@/lib/api';

interface GeneralPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function GeneralPage({ settings, onUpdate }: GeneralPageProps) {
  const isTelegram = settings.site_phone_channel === 'telegram';

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Phone className="h-4 w-4 text-muted-foreground" />
            Admin Phone Number
          </CardTitle>
          <CardDescription>
            Site-level phone number used as a fallback for notifications and available as a template variable in flows.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4 max-w-md">
            <Field>
              <FieldLabel htmlFor="site_phone_channel">Channel</FieldLabel>
              <Select
                value={settings.site_phone_channel}
                onValueChange={(value) => onUpdate('site_phone_channel', value as SitePhoneChannel)}
              >
                <SelectTrigger id="site_phone_channel">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {SITE_PHONE_CHANNELS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field>
              <FieldLabel htmlFor="site_phone">
                {isTelegram ? 'Chat ID' : 'Phone Number'}
              </FieldLabel>
              <Input
                id="site_phone"
                type="text"
                value={settings.site_phone}
                onChange={(e) => onUpdate('site_phone', e.target.value)}
                placeholder={isTelegram ? '123456789' : '+1234567890'}
              />
              <FieldDescription>
                {'Used as {{site.phone}} in flow templates and as fallback for notifications when no recipients are configured.'}
              </FieldDescription>
            </Field>

            {isTelegram && (
              <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
                Telegram requires a configured Telegram bot in Authentication &rarr; Channels.
              </p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
