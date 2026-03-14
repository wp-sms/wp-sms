import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { MethodCard } from '@/components/method-card';
import { UserPlus, ListChecks, ArrowRight } from 'lucide-react';
import type { AuthSettings } from '@/lib/api';

interface RegistrationProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function Registration({ settings, onUpdate }: RegistrationProps) {
  const fieldCount = (settings.profile_fields ?? []).length;

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
            Fields shown on the registration form are managed in the Profile Fields page.
            {fieldCount > 0 && ` ${fieldCount} custom field${fieldCount !== 1 ? 's' : ''} configured.`}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="outline" size="sm" onClick={() => window.location.hash = '#profile-fields'}>
            Manage Profile Fields
            <ArrowRight className="ml-1 h-3.5 w-3.5" />
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
