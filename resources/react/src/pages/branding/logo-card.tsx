import { __, sprintf } from '@wordpress/i18n';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import { Image, X, AlignLeft, AlignCenter, AlignRight, EyeOff } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';
import type { BrandingSettings, LogoPosition } from '@/lib/api';

interface LogoCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
}

const LOGO_POSITIONS = [
  { value: 'left' as LogoPosition, label: __('Left', 'wp-sms'), icon: <AlignLeft className="h-4 w-4" /> },
  { value: 'center' as LogoPosition, label: __('Center', 'wp-sms'), icon: <AlignCenter className="h-4 w-4" /> },
  { value: 'right' as LogoPosition, label: __('Right', 'wp-sms'), icon: <AlignRight className="h-4 w-4" /> },
  { value: 'hidden' as LogoPosition, label: __('Hidden', 'wp-sms'), icon: <EyeOff className="h-4 w-4" /> },
];

export function LogoCard({ branding, onChange }: LogoCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Image className="h-4 w-4 text-muted-foreground" />
          {__('Logo & Site Name', 'wp-sms')}
        </CardTitle>
        <CardDescription>
          {__('Customize the logo and name displayed on auth pages', 'wp-sms')}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <Field>
          <FieldLabel>{__('Logo', 'wp-sms')}</FieldLabel>
          <div className="flex items-center gap-3">
            {branding.logo_url ? (
              <div className="relative">
                <img
                  src={branding.logo_url}
                  alt={__('Logo preview', 'wp-sms')}
                  className="h-10 max-w-[160px] rounded border border-input object-contain p-1"
                />
                <button
                  type="button"
                  onClick={() => onChange({ logo_url: '' })}
                  className="absolute -end-1.5 -top-1.5 rounded-md bg-destructive p-0.5 text-destructive-foreground shadow-sm hover:bg-destructive/90"
                >
                  <X className="h-3 w-3" />
                </button>
              </div>
            ) : (
              <div className="flex h-10 w-20 items-center justify-center rounded border-2 border-dashed border-input">
                <Image className="h-5 w-5 text-muted-foreground/50" />
              </div>
            )}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => openMediaLibrary('Select Logo', (url) => onChange({ logo_url: url }))}
            >
              {branding.logo_url ? __('Change', 'wp-sms') : __('Upload', 'wp-sms')}
            </Button>
          </div>
          <FieldDescription>
            {__('Leave empty to use the default WSMS icon.', 'wp-sms')}
          </FieldDescription>
        </Field>

        <div className="max-w-md">
          <Field>
            <FieldLabel htmlFor="branding-site-name">{__('Site Name', 'wp-sms')}</FieldLabel>
            <Input
              id="branding-site-name"
              value={branding.site_name}
              onChange={(e) => onChange({ site_name: e.target.value })}
              placeholder={__('Your site name (defaults to WordPress site name)', 'wp-sms')}
            />
          </Field>
        </div>

        <Field>
          <FieldLabel>{__('Logo Position', 'wp-sms')}</FieldLabel>
          <SegmentedGroup
            value={branding.logo_position}
            onChange={(v) => onChange({ logo_position: v })}
            options={LOGO_POSITIONS}
          />
        </Field>

        <Field>
          <FieldLabel>{sprintf(__('Logo Size: %dpx', 'wp-sms'), branding.logo_size)}</FieldLabel>
          <div className="flex items-center gap-3 max-w-xs">
            <input
              type="range"
              min={20}
              max={80}
              step={2}
              value={branding.logo_size}
              onChange={(e) => onChange({ logo_size: Number(e.target.value) })}
              className="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-muted accent-primary"
            />
            <div className="flex h-8 w-14 shrink-0 items-center justify-center rounded border border-input text-xs font-mono">
              {branding.logo_size} px
            </div>
          </div>
        </Field>
      </CardContent>
    </Card>
  );
}
