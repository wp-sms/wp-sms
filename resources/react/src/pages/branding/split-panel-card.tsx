import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { PanelLeft, X, Image } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';
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
          Split Panel
        </CardTitle>
        <CardDescription>
          Customize the brand panel in split layout
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
              <span className="text-sm">Left</span>
            </label>
            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-input px-3 py-2 transition-colors has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5">
              <RadioGroupItem value="right" />
              <span className="text-sm">Right</span>
            </label>
          </RadioGroup>
        </Field>

        <div className="max-w-md">
          <Field>
            <FieldLabel htmlFor="branding-split-bg-color">Panel Background Color</FieldLabel>
            <div className="flex gap-2">
              <input
                type="color"
                value={branding.split_panel_bg_color}
                onChange={(e) => onChange({ split_panel_bg_color: e.target.value })}
                className="h-9 w-12 cursor-pointer rounded border border-input p-1"
              />
              <Input
                id="branding-split-bg-color"
                value={branding.split_panel_bg_color}
                onChange={(e) => onChange({ split_panel_bg_color: e.target.value })}
                placeholder="#1e293b"
                className="font-mono"
              />
            </div>
          </Field>
        </div>

        <Field>
          <FieldLabel>Panel Background Image</FieldLabel>
          <div className="flex items-center gap-3">
            {branding.split_panel_bg_image_url ? (
              <div className="relative">
                <img
                  src={branding.split_panel_bg_image_url}
                  alt="Panel background preview"
                  className="h-16 w-28 rounded border border-input object-cover"
                />
                <button
                  type="button"
                  onClick={() => onChange({ split_panel_bg_image_url: '' })}
                  className="absolute -right-1.5 -top-1.5 rounded-full bg-destructive p-0.5 text-destructive-foreground shadow-sm hover:bg-destructive/90"
                >
                  <X className="h-3 w-3" />
                </button>
              </div>
            ) : (
              <div className="flex h-16 w-28 items-center justify-center rounded border-2 border-dashed border-input">
                <Image className="h-5 w-5 text-muted-foreground/50" />
              </div>
            )}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => openMediaLibrary('Select Panel Background', (url) => onChange({ split_panel_bg_image_url: url }))}
            >
              {branding.split_panel_bg_image_url ? 'Change' : 'Upload'}
            </Button>
          </div>
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
              placeholder="Welcome back"
            />
          </Field>

          <Field>
            <FieldLabel htmlFor="branding-split-subtitle">Subtitle</FieldLabel>
            <Input
              id="branding-split-subtitle"
              value={branding.split_subtitle}
              onChange={(e) => onChange({ split_subtitle: e.target.value })}
              placeholder="Sign in to continue"
            />
          </Field>
        </div>
      </CardContent>
    </Card>
  );
}
