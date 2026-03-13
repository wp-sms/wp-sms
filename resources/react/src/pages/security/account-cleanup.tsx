import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
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
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Pending Registration Cleanup</CardTitle>
        <CardDescription>
          When verification is required at signup, users who never complete verification are automatically cleaned up after the TTL expires. This frees their email/phone for re-registration.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <Field className="flex items-center justify-between">
          <div>
            <FieldLabel htmlFor="cleanup_enabled">Enable automatic cleanup</FieldLabel>
            <FieldDescription>Automatically delete pending users who never verified their account</FieldDescription>
          </div>
          <Switch
            id="cleanup_enabled"
            checked={settings.pending_user_cleanup_enabled}
            onCheckedChange={(v) => onUpdate('pending_user_cleanup_enabled', v)}
          />
        </Field>

        {settings.pending_user_cleanup_enabled && (
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
        )}
      </CardContent>
    </Card>
  );
}
