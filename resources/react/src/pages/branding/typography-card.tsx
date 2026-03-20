import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup, SelectLabel } from '@/components/ui/select';
import { Type } from 'lucide-react';
import { SYSTEM_FONTS, GOOGLE_FONTS, ALL_FONTS } from './font-list';
import type { BrandingSettings } from '@/lib/api';

interface TypographyCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
}

export function TypographyCard({ branding, onChange }: TypographyCardProps) {
  function handleFontChange(value: string) {
    const font = ALL_FONTS.find((f) => f.value === value);
    onChange({
      font_family: value,
      google_font: font?.google ?? false,
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Type className="h-4 w-4 text-muted-foreground" />
          Typography
        </CardTitle>
        <CardDescription>
          Choose a font family for auth pages
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="max-w-md">
          <Field>
            <FieldLabel htmlFor="branding-font-family">Font Family</FieldLabel>
            <Select value={branding.font_family} onValueChange={handleFontChange}>
              <SelectTrigger id="branding-font-family">
                <SelectValue placeholder="Select a font" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectLabel>System Fonts</SelectLabel>
                  {SYSTEM_FONTS.map((font) => (
                    <SelectItem key={font.value} value={font.value}>
                      {font.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
                <SelectGroup>
                  <SelectLabel>Google Fonts</SelectLabel>
                  {GOOGLE_FONTS.map((font) => (
                    <SelectItem key={font.value} value={font.value}>
                      {font.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
            <FieldDescription>
              Google Fonts are loaded with display=swap for fast rendering
            </FieldDescription>
          </Field>
        </div>
      </CardContent>
    </Card>
  );
}
