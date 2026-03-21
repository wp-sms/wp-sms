import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { LogIn, CreditCard } from 'lucide-react';
import type { AuthSettings, WooCommerceSettings } from '@/lib/api';

interface WooCommerceProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const CHECKOUT_TOGGLES: { id: string; key: keyof WooCommerceSettings; label: string; description: string; defaultChecked?: boolean }[] = [
  { id: 'woo_checkout_email', key: 'verify_email_at_checkout', label: 'Verify email at checkout', description: 'Customers must verify their billing email before completing checkout' },
  { id: 'woo_checkout_phone', key: 'verify_phone_at_checkout', label: 'Verify phone at checkout', description: 'Customers must verify their billing phone number before completing checkout' },
  { id: 'woo_skip_verified', key: 'skip_verified_users', label: 'Skip for verified users', description: 'Logged-in users who have already verified their email/phone skip the verification step', defaultChecked: true },
];

export function WooCommerce({ settings, onUpdate }: WooCommerceProps) {
  const woo = settings.woocommerce ?? {};

  function update(partial: Partial<WooCommerceSettings>) {
    onUpdate('woocommerce', { ...woo, ...partial });
  }

  return (
    <div className="space-y-6">
      <Card active={!!woo.redirect_auth}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <LogIn className="h-4 w-4 text-muted-foreground" />
            Authentication
          </CardTitle>
          <CardDescription>
            Redirect WooCommerce My Account login and registration to WSMS auth pages.
            Ensures all channel settings (phone login, passwordless, MFA) are respected.
          </CardDescription>
          <CardAction>
            <Switch
              checked={!!woo.redirect_auth}
              onCheckedChange={(v) => update({ redirect_auth: v })}
              aria-label="Toggle WooCommerce auth redirect"
            />
          </CardAction>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <CreditCard className="h-4 w-4 text-muted-foreground" />
            Checkout Verification
          </CardTitle>
          <CardDescription>
            Require customers to verify their email or phone with an OTP code before placing an order. Works with both classic and block checkout.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {CHECKOUT_TOGGLES.map((toggle, i) => (
            <div key={toggle.id}>
              {i > 0 && <Separator className="mb-4" />}
              <div className="flex items-start justify-between gap-4">
                <div>
                  <label htmlFor={toggle.id} className="text-sm font-medium leading-snug">{toggle.label}</label>
                  <p className="text-sm text-muted-foreground">{toggle.description}</p>
                </div>
                <Switch
                  id={toggle.id}
                  className="mt-0.5"
                  checked={toggle.defaultChecked ? woo[toggle.key] !== false : !!woo[toggle.key]}
                  onCheckedChange={(v) => update({ [toggle.key]: v })}
                />
              </div>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
