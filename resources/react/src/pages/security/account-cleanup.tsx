import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Trash2 } from 'lucide-react';
import type { AuthSettings } from '@/lib/api';

interface AccountCleanupProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

function formatTtl(hours: number): string {
  if (hours >= 24) {
    const days = Math.floor(hours / 24);
    return `${days} day${days !== 1 ? 's' : ''}`;
  }
  return `${hours} hours`;
}

export function AccountCleanup({ settings, onUpdate }: AccountCleanupProps) {
  const enabled = settings.pending_user_cleanup_enabled;

  return (
    <Card className={enabled ? 'border-l-2 border-l-primary' : 'opacity-50'}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Trash2 className="h-4 w-4 text-muted-foreground" />
          Pending Registration Cleanup
        </CardTitle>
        <CardDescription>
          Automatically delete pending users who never verified their account.
          When verification is required at signup, users who never complete verification are cleaned up after the TTL expires, freeing their email/phone for re-registration.
        </CardDescription>
        <CardAction>
          <Switch
            checked={enabled}
            onCheckedChange={(v) => onUpdate('pending_user_cleanup_enabled', v)}
            aria-label="Toggle pending registration cleanup"
          />
        </CardAction>
      </CardHeader>
      {enabled && (
        <CardContent className="border-t pt-4">
          <Field>
            <FieldLabel htmlFor="ttl_hours">Cleanup TTL (hours)</FieldLabel>
            <Input
              id="ttl_hours"
              type="number"
              min={1}
              max={720}
              value={settings.pending_user_ttl_hours}
              onChange={(e) => onUpdate('pending_user_ttl_hours', Number(e.target.value))}
              className="max-w-[200px]"
            />
            <FieldDescription>
              Hours before an unverified registration is eligible for cleanup and re-registration ({formatTtl(settings.pending_user_ttl_hours)})
            </FieldDescription>
          </Field>
        </CardContent>
      )}
    </Card>
  );
}
