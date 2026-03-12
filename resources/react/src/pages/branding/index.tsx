import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Globe } from 'lucide-react';
import { ComingSoon } from '@/components/coming-soon';
import type { AuthSettings } from '@/lib/api';

interface BrandingPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function BrandingPage({ settings, onUpdate }: BrandingPageProps) {
  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Globe className="h-4 w-4 text-muted-foreground" />
            Auth Pages
          </CardTitle>
          <CardDescription>
            Configure authentication page URLs and login behavior
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="max-w-md">
              <Field>
                <FieldLabel htmlFor="auth_base_url">Base URL</FieldLabel>
                <Input
                  id="auth_base_url"
                  type="text"
                  value={settings.auth_base_url}
                  onChange={(e) => onUpdate('auth_base_url', e.target.value)}
                  placeholder="/auth"
                />
                <FieldDescription>
                  The base path for authentication pages (e.g., /auth, /login)
                </FieldDescription>
              </Field>
            </div>

            <Field orientation="horizontal">
              <FieldLabel htmlFor="redirect_login">Redirect WordPress Login</FieldLabel>
              <Switch
                id="redirect_login"
                checked={settings.redirect_login}
                onCheckedChange={(checked) => onUpdate('redirect_login', checked)}
                aria-label="Toggle redirect login"
              />
              <FieldDescription>
                Redirect wp-login.php to your custom auth pages
              </FieldDescription>
            </Field>

            {settings.redirect_login && (
              <p className="text-xs text-muted-foreground mt-3 rounded-md bg-muted/50 p-3">
                When enabled, visitors to <code className="rounded bg-muted px-1 py-0.5 font-mono text-[0.6875rem]">wp-login.php</code> will be redirected to <code className="rounded bg-muted px-1 py-0.5 font-mono text-[0.6875rem]">{settings.auth_base_url}/login</code>
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      <ComingSoon
        title="Custom Logo & Colors"
        description="Upload your logo and customize the color scheme of auth pages"
      />

      <ComingSoon
        title="Email Templates"
        description="Customize the email templates for OTP codes and magic links"
      />
    </div>
  );
}
