import { __ } from '@wordpress/i18n';
import { useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup, SelectLabel } from '@/components/ui/select';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import { ColorPickerField } from '@/components/ui/color-picker-field';
import { PresetGrid } from '@/components/ui/preset-grid';
import { ImagePickerField } from '@/components/ui/image-picker-field';
import { Palette, Sun, Moon, Monitor } from 'lucide-react';
import { COLOR_PRESETS, getActivePresetId } from './color-presets';
import { SYSTEM_FONTS, GOOGLE_FONTS, ALL_FONTS } from './font-list';
import type { BrandingSettings, ColorMode } from '@/lib/api';

const COLOR_MODE_OPTIONS = [
  { value: 'light' as ColorMode, label: __('Light', 'wp-sms'), icon: <Sun className="h-4 w-4" /> },
  { value: 'dark' as ColorMode, label: __('Dark', 'wp-sms'), icon: <Moon className="h-4 w-4" /> },
  { value: 'auto' as ColorMode, label: __('Auto', 'wp-sms'), icon: <Monitor className="h-4 w-4" /> },
];

interface ColorsCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
}

export function ColorsCard({ branding, onChange }: ColorsCardProps) {
  const activePresetId = getActivePresetId(branding);
  const isCentered = branding.layout === 'centered';

  useEffect(() => {
    const font = ALL_FONTS.find((f) => f.value === branding.font_family);
    if (!font?.google) return;
    const id = `google-font-${font.value.replace(/\s+/g, '-')}`;
    if (document.getElementById(id)) return;
    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(font.value)}:wght@400;500;600&display=swap`;
    document.head.appendChild(link);
    return () => { link.remove(); };
  }, [branding.font_family]);

  function applyPreset(presetId: string) {
    const preset = COLOR_PRESETS.find((p) => p.id === presetId);
    if (!preset) return;
    onChange({
      primary_color: preset.primary_color,
      accent_color: preset.accent_color,
      text_color: preset.text_color,
      error_color: preset.error_color,
      background_color: preset.background_color,
      split_panel_bg_color: preset.split_panel_bg_color,
    });
  }

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
          <Palette className="h-4 w-4 text-muted-foreground" />
          {__('Colors & Typography', 'wp-sms')}
        </CardTitle>
        <CardDescription>
          {__('Pick a preset theme, customize colors, and choose a font', 'wp-sms')}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <Field>
          <FieldLabel>{__('Theme Preset', 'wp-sms')}</FieldLabel>
          <PresetGrid
            presets={COLOR_PRESETS}
            activePresetId={activePresetId}
            onSelect={(preset) => applyPreset(preset.id)}
            renderPreview={(preset) => (
              <div className="h-8 w-full rounded" style={{ backgroundColor: preset.background_color }}>
                <div className="mx-auto mt-1.5 h-1.5 w-3/4 rounded-full" style={{ backgroundColor: preset.primary_color }} />
                <div className="mx-auto mt-1 flex justify-center gap-1">
                  <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: preset.accent_color }} />
                </div>
              </div>
            )}
          />
        </Field>

        <Field>
          <FieldLabel>{__('Color Mode', 'wp-sms')}</FieldLabel>
          <SegmentedGroup
            value={branding.color_mode}
            onChange={(v) => onChange({ color_mode: v })}
            options={COLOR_MODE_OPTIONS}
            size="labeled"
          />
          {branding.color_mode === 'auto' && (
            <FieldDescription>
              {__("Automatically switches between light and dark based on visitor's system preference.", 'wp-sms')}
            </FieldDescription>
          )}
        </Field>

        <div className="grid grid-cols-2 gap-4 max-w-md">
          <ColorPickerField
            id="branding-primary-color"
            label={__('Primary', 'wp-sms')}
            value={branding.primary_color}
            placeholder="#8b5320"
            onChange={(v) => onChange({ primary_color: v })}
          />
          <ColorPickerField
            id="branding-accent-color"
            label={__('Accent', 'wp-sms')}
            value={branding.accent_color}
            placeholder="#6366f1"
            onChange={(v) => onChange({ accent_color: v })}
          />
          <ColorPickerField
            id="branding-text-color"
            label={__('Text', 'wp-sms')}
            value={branding.text_color}
            placeholder="#1c1917"
            onChange={(v) => onChange({ text_color: v })}
          />
          <ColorPickerField
            id="branding-error-color"
            label={__('Error', 'wp-sms')}
            value={branding.error_color}
            placeholder="#dc2626"
            onChange={(v) => onChange({ error_color: v })}
          />
        </div>

        {/* Background section — only for centered layout */}
        {isCentered && (
          <>
            <div className="max-w-md">
              <ColorPickerField
                id="branding-bg-color"
                label={__('Background Color', 'wp-sms')}
                value={branding.background_color}
                placeholder="#f5f5f4"
                onChange={(v) => onChange({ background_color: v })}
              />
            </div>

            <Field>
              <FieldLabel>{__('Background Image', 'wp-sms')}</FieldLabel>
              <ImagePickerField
                value={branding.background_image_url}
                title={__('Select Background Image', 'wp-sms')}
                alt={__('Background preview', 'wp-sms')}
                onSelect={(url) => onChange({ background_image_url: url })}
                onClear={() => onChange({ background_image_url: '' })}
              />
              <FieldDescription>
                {__('Covers the page behind the login card. Optional.', 'wp-sms')}
              </FieldDescription>
            </Field>
          </>
        )}

        <div className="max-w-md">
          <Field>
            <FieldLabel htmlFor="branding-font-family">{__('Font Family', 'wp-sms')}</FieldLabel>
            <Select value={branding.font_family} onValueChange={handleFontChange}>
              <SelectTrigger id="branding-font-family">
                <SelectValue placeholder={__('Select a font', 'wp-sms')} />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectLabel>{__('System Fonts', 'wp-sms')}</SelectLabel>
                  {SYSTEM_FONTS.map((font) => (
                    <SelectItem key={font.value} value={font.value}>
                      {font.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
                <SelectGroup>
                  <SelectLabel>{__('Google Fonts', 'wp-sms')}</SelectLabel>
                  {GOOGLE_FONTS.map((font) => (
                    <SelectItem key={font.value} value={font.value}>
                      {font.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
            <FieldDescription>
              {__('Google Fonts are loaded with display=swap for fast rendering', 'wp-sms')}
            </FieldDescription>
          </Field>

          {/* Live font preview */}
          <div
            className="mt-3 rounded-md border border-input bg-muted/30 px-3 py-2 text-sm"
            style={{ fontFamily: branding.font_family || 'system-ui' }}
          >
            The quick brown fox jumps over the lazy dog
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
