import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field';
import { MethodCard } from '@/components/method-card';
import { UserPlus, ListChecks } from 'lucide-react';
import { REGISTRATION_FIELDS, toggleArrayItem } from '@/lib/constants';
import type { AuthSettings } from '@/lib/api';

interface RegistrationProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function Registration({ settings, onUpdate }: RegistrationProps) {
  const fields = settings.registration_fields;

  return (
    <div className="space-y-4">
      <MethodCard
        title="Auto-Create Accounts on Login"
        description="When someone logs in with a phone or email that doesn't have an account yet, automatically create one instead of rejecting them"
        icon={UserPlus}
        enabled={settings.auto_create_users}
        onToggle={(checked) => onUpdate('auto_create_users', checked)}
      />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <ListChecks className="h-4 w-4 text-muted-foreground" />
            Registration Fields
          </CardTitle>
          <CardDescription>
            Select additional fields to show on the registration form
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-lg border border-border/50 divide-y divide-border/50">
            {REGISTRATION_FIELDS.map((field) => (
              <div key={field.id} className="flex items-center gap-3 px-4 py-3">
                <Checkbox
                  id={`field-${field.id}`}
                  checked={fields.includes(field.id)}
                  onCheckedChange={(checked) => onUpdate('registration_fields', toggleArrayItem(fields, field.id, !!checked))}
                  aria-label={`Toggle ${field.label}`}
                />
                <FieldLabel htmlFor={`field-${field.id}`} className="cursor-pointer">
                  {field.label}
                </FieldLabel>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
