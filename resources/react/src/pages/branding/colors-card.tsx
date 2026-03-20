import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import { Palette, X, Image, Sun, Moon, Monitor } from 'lucide-react';
import { openMediaLibrary } from '@/lib/media';
import { COLOR_PRESETS, getActivePresetId } from './color-presets';
import type { BrandingSettings, ColorMode } from '@/lib/api';

const COLOR_MODE_OPTIONS = [
  { value: 'light' as ColorMode, label: 'Light', icon: <Sun className="h-4 w-4" /> },
  { value: 'dark' as ColorMode, label: 'Dark', icon: <Moon className="h-4 w-4" /> },
  { value: 'auto' as ColorMode, label: 'Auto', icon: <Monitor className="h-4 w-4" /> },
];

interface ColorPickerFieldProps {
  id: string;
  label: string;
  value: string;
  placeholder: string;
  onChange: (value: string) => void;
}

function ColorPickerField({ id, label, value, placeholder, onChange }: ColorPickerFieldProps) {
  return (
    <Field>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <div className="flex gap-2">
        <input
          type="color"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-9 w-12 cursor-pointer rounded border border-input p-1"
        />
        <Input
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="font-mono"
        />
      </div>
    </Field>
  );
}

interface ColorsCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
}

export function ColorsCard({ branding, onChange }: ColorsCardProps) {
  const activePresetId = getActivePresetId(branding);
  const isCentered = branding.layout === 'centered';

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

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Palette className="h-4 w-4 text-muted-foreground" />
          Colors
        </CardTitle>
        <CardDescription>
          Pick a preset theme or customize individual colors
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {/* Preset swatches */}
        <div className="space-y-2">
          <span className="text-sm font-medium">Theme Preset</span>
          <div className="flex flex-wrap gap-2">
            {COLOR_PRESETS.map((preset) => (
              <button
                key={preset.id}
                type="button"
                title={preset.name}
                onClick={() => applyPreset(preset.id)}
                className={`flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium transition-colors ${
                  activePresetId === preset.id
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-input text-muted-foreground hover:border-foreground/20 hover:text-foreground'
                }`}
              >
                <span
                  className="h-3.5 w-3.5 rounded-full border border-black/10"
                  style={{ backgroundColor: preset.primary_color }}
                />
                {preset.name}
              </button>
            ))}
            {activePresetId === 'custom' && (
              <span className="flex items-center gap-1.5 rounded-md border border-dashed border-input px-2.5 py-1.5 text-xs font-medium text-muted-foreground">
                Custom
              </span>
            )}
          </div>
        </div>

        {/* Color mode */}
        <div className="space-y-2">
          <span className="text-sm font-medium">Color Mode</span>
          <SegmentedGroup
            value={branding.color_mode}
            onChange={(v) => onChange({ color_mode: v })}
            options={COLOR_MODE_OPTIONS}
            size="labeled"
          />
          {branding.color_mode === 'auto' && (
            <p className="text-xs text-muted-foreground">
              Automatically switches between light and dark based on visitor's system preference.
            </p>
          )}
        </div>

        {/* Color grid */}
        <div className="grid grid-cols-2 gap-4 max-w-md">
          <ColorPickerField
            id="branding-primary-color"
            label="Primary"
            value={branding.primary_color}
            placeholder="#8b5320"
            onChange={(v) => onChange({ primary_color: v })}
          />
          <ColorPickerField
            id="branding-accent-color"
            label="Accent"
            value={branding.accent_color}
            placeholder="#6366f1"
            onChange={(v) => onChange({ accent_color: v })}
          />
          <ColorPickerField
            id="branding-text-color"
            label="Text"
            value={branding.text_color}
            placeholder="#1c1917"
            onChange={(v) => onChange({ text_color: v })}
          />
          <ColorPickerField
            id="branding-error-color"
            label="Error"
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
                label="Background Color"
                value={branding.background_color}
                placeholder="#f5f5f4"
                onChange={(v) => onChange({ background_color: v })}
              />
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
      </CardContent>
    </Card>
  );
}
