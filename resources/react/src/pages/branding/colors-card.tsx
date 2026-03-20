import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Palette, X, Image } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';
import type { BrandingSettings } from '@/lib/api';

interface ColorsCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
  showBackground: boolean;
}

export function ColorsCard({ branding, onChange, showBackground }: ColorsCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Palette className="h-4 w-4 text-muted-foreground" />
          Colors
        </CardTitle>
        <CardDescription>
          Set the primary brand color, background, and dark mode
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="max-w-md">
          <Field>
            <FieldLabel htmlFor="branding-primary-color">Primary Color</FieldLabel>
            <div className="flex gap-2">
              <input
                type="color"
                value={branding.primary_color}
                onChange={(e) => onChange({ primary_color: e.target.value })}
                className="h-9 w-12 cursor-pointer rounded border border-input p-1"
              />
              <Input
                id="branding-primary-color"
                value={branding.primary_color}
                onChange={(e) => onChange({ primary_color: e.target.value })}
                placeholder="#8b5320"
                className="font-mono"
              />
            </div>
            <FieldDescription>
              Used for buttons, links, and interactive elements
            </FieldDescription>
          </Field>
        </div>

        {showBackground && (
          <>
            <div className="max-w-md">
              <Field>
                <FieldLabel htmlFor="branding-bg-color">Background Color</FieldLabel>
                <div className="flex gap-2">
                  <input
                    type="color"
                    value={branding.background_color}
                    onChange={(e) => onChange({ background_color: e.target.value })}
                    className="h-9 w-12 cursor-pointer rounded border border-input p-1"
                  />
                  <Input
                    id="branding-bg-color"
                    value={branding.background_color}
                    onChange={(e) => onChange({ background_color: e.target.value })}
                    placeholder="#f5f5f4"
                    className="font-mono"
                  />
                </div>
              </Field>
            </div>

            <Field>
              <FieldLabel>Background Image</FieldLabel>
              <div className="flex items-center gap-3">
                {branding.background_image_url ? (
                  <div className="relative">
                    <img
                      src={branding.background_image_url}
                      alt="Background preview"
                      className="h-16 w-28 rounded border border-input object-cover"
                    />
                    <button
                      type="button"
                      onClick={() => onChange({ background_image_url: '' })}
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
                  onClick={() => openMediaLibrary('Select Background Image', (url) => onChange({ background_image_url: url }))}
                >
                  {branding.background_image_url ? 'Change' : 'Upload'}
                </Button>
              </div>
              <FieldDescription>
                Covers the page behind the login card. Optional.
              </FieldDescription>
            </Field>
          </>
        )}

        <Field orientation="horizontal">
          <FieldLabel htmlFor="branding-dark-mode">Dark Mode</FieldLabel>
          <Switch
            id="branding-dark-mode"
            checked={branding.dark_mode}
            onCheckedChange={(checked) => onChange({ dark_mode: checked })}
            aria-label="Toggle dark mode"
          />
          <FieldDescription>
            Inverts the color scheme for a dark appearance
          </FieldDescription>
        </Field>
      </CardContent>
    </Card>
  );
}
