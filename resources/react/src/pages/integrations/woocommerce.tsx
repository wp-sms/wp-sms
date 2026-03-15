import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import type { AuthSettings, WooCommerceSettings } from '@/lib/api';

interface WooCommerceProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function WooCommerce({ settings, onUpdate }: WooCommerceProps) {
  const woo = settings.woocommerce ?? {};

  function update(partial: Partial<WooCommerceSettings>) {
    onUpdate('woocommerce', { ...woo, ...partial });
  }

  return (
    <div className="space-y-6">
      {/* Auth Redirect */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Authentication</CardTitle>
          <CardDescription>
            Control how customers log in and register through WooCommerce pages.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Field className="flex items-center justify-between">
            <div>
              <FieldLabel htmlFor="woo_redirect_auth">Redirect login & registration</FieldLabel>
              <FieldDescription>
                Redirect WooCommerce My Account login and registration to WSMS auth pages.
                Ensures all channel settings (phone login, passwordless, MFA) are respected.
              </FieldDescription>
            </div>
            <Switch
              id="woo_redirect_auth"
              checked={!!woo.redirect_auth}
              onCheckedChange={(v) => update({ redirect_auth: v })}
            />
          </Field>
        </CardContent>
      </Card>

      {/* Checkout Verification */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Checkout Verification</CardTitle>
          <CardDescription>
            Require customers to verify their email or phone with an OTP code before placing an order. Works with both classic and block checkout.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Field className="flex items-center justify-between">
            <div>
              <FieldLabel htmlFor="woo_checkout_email">Verify email at checkout</FieldLabel>
              <FieldDescription>Customers must verify their billing email before completing checkout</FieldDescription>
            </div>
            <Switch
              id="woo_checkout_email"
              checked={!!woo.verify_email_at_checkout}
              onCheckedChange={(v) => update({ verify_email_at_checkout: v })}
            />
          </Field>

          <Separator />

          <Field className="flex items-center justify-between">
            <div>
              <FieldLabel htmlFor="woo_checkout_phone">Verify phone at checkout</FieldLabel>
              <FieldDescription>Customers must verify their billing phone number before completing checkout</FieldDescription>
            </div>
            <Switch
              id="woo_checkout_phone"
              checked={!!woo.verify_phone_at_checkout}
              onCheckedChange={(v) => update({ verify_phone_at_checkout: v })}
            />
          </Field>

          <Separator />

          <Field className="flex items-center justify-between">
            <div>
              <FieldLabel htmlFor="woo_skip_verified">Skip for verified users</FieldLabel>
              <FieldDescription>Logged-in users who have already verified their email/phone skip the verification step</FieldDescription>
            </div>
            <Switch
              id="woo_skip_verified"
              checked={woo.skip_verified_users !== false}
              onCheckedChange={(v) => update({ skip_verified_users: v })}
            />
          </Field>
        </CardContent>
      </Card>
    </div>
  );
}
