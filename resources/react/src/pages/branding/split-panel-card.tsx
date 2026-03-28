import { __ } from '@wordpress/i18n';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ColorPickerField } from '@/components/ui/color-picker-field';
import { ImagePickerField } from '@/components/ui/image-picker-field';
import { PanelLeft } from 'lucide-react';
import type { BrandingSettings, SplitPanelPosition } from '@/lib/api';

interface SplitPanelCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
}

export function SplitPanelCard({ branding, onChange }: SplitPanelCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <PanelLeft className="h-4 w-4 text-muted-foreground" />
          {__('Split Panel', 'wp-sms')}
        </CardTitle>
        <CardDescription>
          {__('Customize the brand panel in split layout', 'wp-sms')}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <Field>
          <FieldLabel>Panel Position</FieldLabel>
          <RadioGroup
            value={branding.split_panel_position}
            onValueChange={(v) => onChange({ split_panel_position: v as SplitPanelPosition })}
            className="flex gap-3 pt-1"
          >
            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-input px-3 py-2 transition-colors has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5">
              <RadioGroupItem value="left" />
              <span className="text-sm">{__('Left', 'wp-sms')}</span>
            </label>
            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-input px-3 py-2 transition-colors has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5">
              <RadioGroupItem value="right" />
              <span className="text-sm">{__('Right', 'wp-sms')}</span>
            </label>
          </RadioGroup>
        </Field>

        <div className="max-w-md">
          <ColorPickerField
            id="branding-split-bg-color"
            label="Panel Background Color"
            value={branding.split_panel_bg_color}
            placeholder="#1e293b"
            onChange={(v) => onChange({ split_panel_bg_color: v })}
          />
        </div>

        <Field>
          <FieldLabel>Panel Background Image</FieldLabel>
          <ImagePickerField
            value={branding.split_panel_bg_image_url}
            title={__('Select Panel Background', 'wp-sms')}
            alt="Panel background preview"
            onSelect={(url) => onChange({ split_panel_bg_image_url: url })}
            onClear={() => onChange({ split_panel_bg_image_url: '' })}
          />
          <FieldDescription>
            Optional image for the brand panel. Color shows through if not set.
          </FieldDescription>
        </Field>

        <div className="max-w-md space-y-4">
          <Field>
            <FieldLabel htmlFor="branding-split-heading">Welcome Heading</FieldLabel>
            <Input
              id="branding-split-heading"
              value={branding.split_welcome_heading}
              onChange={(e) => onChange({ split_welcome_heading: e.target.value })}
              placeholder={__('Welcome back', 'wp-sms')}
            />
          </Field>

          <Field>
            <FieldLabel htmlFor="branding-split-subtitle">Subtitle</FieldLabel>
            <Input
              id="branding-split-subtitle"
              value={branding.split_subtitle}
              onChange={(e) => onChange({ split_subtitle: e.target.value })}
              placeholder={__('Sign in to continue', 'wp-sms')}
            />
          </Field>
        </div>
      </CardContent>
    </Card>
  );
}
