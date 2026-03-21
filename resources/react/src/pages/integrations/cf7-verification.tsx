import { Card, CardAction, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { FileCheck } from 'lucide-react';
import type { AuthSettings, ContactForm7Settings } from '@/lib/api';

interface CF7VerificationProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function CF7Verification({ settings, onUpdate }: CF7VerificationProps) {
  const cf7 = settings.contact_form_7 ?? {};
  const enabled = cf7.verification_enabled !== false;

  function update(partial: Partial<ContactForm7Settings>) {
    onUpdate('contact_form_7', { ...cf7, ...partial });
  }

  return (
    <Card active={enabled}>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <FileCheck className="h-4 w-4 text-muted-foreground" />
          Form Verification
        </CardTitle>
        <CardDescription>
          Add email and phone OTP verification fields to Contact Form 7 forms.
          Registers <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">[wsms_verify_email]</code> and <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">[wsms_verify_phone]</code> form
          tags in the Contact Form 7 editor. Visitors must verify before the form can be submitted.
        </CardDescription>
        <CardAction>
          <Switch
            checked={enabled}
            onCheckedChange={(v) => update({ verification_enabled: v })}
            aria-label="Toggle CF7 verification"
          />
        </CardAction>
      </CardHeader>
    </Card>
  );
}
