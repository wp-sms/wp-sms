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
import type { SocialProviderSettings } from '@/lib/api';

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
            Configure {providerLabel} OAuth credentials
          </DrawerDescription>
        </DrawerHeader>

        <div className="space-y-6 px-4 pb-4">
          <Field>
            <FieldLabel htmlFor={`${providerId}-client-id`}>Client ID</FieldLabel>
            <Input
              id={`${providerId}-client-id`}
              type="text"
              value={settings.client_id ?? ''}
              onChange={(e) => onUpdate({ client_id: e.target.value })}
              placeholder="Enter client ID"
            />
            <FieldDescription>
              From your {providerLabel} developer console
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
              Keep this secret. Never expose it in frontend code.
            </FieldDescription>
          </Field>
        </div>
      </DrawerContent>
    </Drawer>
  );
}
