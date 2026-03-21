import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import type { AuthSettings, ContactForm7Settings } from '@/lib/api';

interface CF7VerificationProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function CF7Verification({ settings, onUpdate }: CF7VerificationProps) {
  const cf7 = settings.contact_form_7 ?? {};

  function update(partial: Partial<ContactForm7Settings>) {
    onUpdate('contact_form_7', { ...cf7, ...partial });
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Form Verification</CardTitle>
          <CardDescription>
            Add email and phone OTP verification fields to Contact Form 7 forms.
            Visitors must verify before the form can be submitted.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Field className="flex items-center justify-between">
            <div>
              <FieldLabel htmlFor="cf7_verification_enabled">Enable verification form tags</FieldLabel>
              <FieldDescription>
                Registers <code>[wsms_verify_email]</code> and <code>[wsms_verify_phone]</code> form
                tags in the Contact Form 7 editor.
              </FieldDescription>
            </div>
            <Switch
              id="cf7_verification_enabled"
              checked={cf7.verification_enabled !== false}
              onCheckedChange={(v) => update({ verification_enabled: v })}
            />
          </Field>
        </CardContent>
      </Card>
    </div>
  );
}
