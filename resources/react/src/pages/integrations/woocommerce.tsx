import { __ } from '@wordpress/i18n';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { SwitchField } from '@/components/ui/field';
import { LogIn, CreditCard } from 'lucide-react';
import type { AuthSettings, WooCommerceSettings } from '@/lib/api';

interface WooCommerceProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

const CHECKOUT_TOGGLES: { id: string; key: keyof WooCommerceSettings; label: string; description: string; defaultChecked?: boolean }[] = [
  { id: 'woo_checkout_email', key: 'verify_email_at_checkout', label: __('Verify email at checkout', 'wp-sms'), description: __('Customers must verify their billing email before completing checkout', 'wp-sms') },
  { id: 'woo_checkout_phone', key: 'verify_phone_at_checkout', label: __('Verify phone at checkout', 'wp-sms'), description: __('Customers must verify their billing phone number before completing checkout', 'wp-sms') },
  { id: 'woo_skip_verified', key: 'skip_verified_users', label: __('Skip for verified users', 'wp-sms'), description: __('Logged-in users who have already verified their email/phone skip the verification step', 'wp-sms'), defaultChecked: true },
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
            {__('Authentication', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Redirect WooCommerce My Account login and registration to WSMS auth pages. Ensures all channel settings (phone login, passwordless, MFA) are respected.', 'wp-sms')}
          </CardDescription>
          <CardAction>
            <Switch
              checked={!!woo.redirect_auth}
              onCheckedChange={(v) => update({ redirect_auth: v })}
              aria-label={__('Toggle WooCommerce auth redirect', 'wp-sms')}
            />
          </CardAction>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <CreditCard className="h-4 w-4 text-muted-foreground" />
            {__('Checkout Verification', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Require customers to verify their email or phone with an OTP code before placing an order. Works with both classic and block checkout.', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {CHECKOUT_TOGGLES.map((toggle, i) => (
            <div key={toggle.id}>
              {i > 0 && <Separator className="mb-4" />}
              <SwitchField
                id={toggle.id}
                label={toggle.label}
                description={toggle.description}
                checked={toggle.defaultChecked ? woo[toggle.key] !== false : !!woo[toggle.key]}
                onCheckedChange={(v) => update({ [toggle.key]: v })}
              />
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
