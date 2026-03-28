import { __ } from '@wordpress/i18n';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import { Layout } from 'lucide-react';
import type { BrandingSettings, BrandingLayout, ButtonStyle, SocialPosition } from '@/lib/api';

const RADIUS_OPTIONS = [
  { value: 0, label: __('Sharp', 'wp-sms'), icon: <div className="h-4 w-4 border-2 border-current" style={{ borderRadius: 0 }} /> },
  { value: 8, label: __('Rounded', 'wp-sms'), icon: <div className="h-4 w-4 border-2 border-current" style={{ borderRadius: '6px' }} /> },
  { value: 16, label: __('Pill', 'wp-sms'), icon: <div className="h-4 w-4 border-2 border-current" style={{ borderRadius: '10px' }} /> },
];

const BUTTON_STYLE_OPTIONS = [
  {
    value: 'filled' as ButtonStyle,
    label: __('Filled', 'wp-sms'),
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" stroke="none">
        <rect x="2" y="5" width="12" height="6" rx="2" />
      </svg>
    ),
  },
  {
    value: 'outline' as ButtonStyle,
    label: __('Outline', 'wp-sms'),
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5">
        <rect x="2" y="5" width="12" height="6" rx="2" />
      </svg>
    ),
  },
  {
    value: 'ghost' as ButtonStyle,
    label: __('Ghost', 'wp-sms'),
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" opacity="0.5">
        <rect x="2" y="5" width="12" height="6" rx="2" strokeDasharray="2 1.5" />
      </svg>
    ),
  },
];

const SOCIAL_POSITIONS = [
  {
    value: 'top' as SocialPosition,
    label: __('Top', 'wp-sms'),
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5">
        <rect x="2" y="2" width="12" height="3" rx="0.5" />
        <line x1="2" y1="7.5" x2="14" y2="7.5" strokeDasharray="2 1" opacity="0.4" />
        <rect x="2" y="10" width="12" height="4" rx="0.5" opacity="0.3" />
      </svg>
    ),
  },
  {
    value: 'bottom' as SocialPosition,
    label: __('Bottom', 'wp-sms'),
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5">
        <rect x="2" y="2" width="12" height="4" rx="0.5" opacity="0.3" />
        <line x1="2" y1="8.5" x2="14" y2="8.5" strokeDasharray="2 1" opacity="0.4" />
        <rect x="2" y="11" width="12" height="3" rx="0.5" />
      </svg>
    ),
  },
];

interface LayoutCardProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
}

export function LayoutCard({ branding, onChange }: LayoutCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Layout className="h-4 w-4 text-muted-foreground" />
          Layout &amp; Shape
        </CardTitle>
        <CardDescription>
          {__('Choose a page layout, corner style, and social button placement', 'wp-sms')}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <Field>
          <FieldLabel>Page Layout</FieldLabel>
          <div className="grid grid-cols-2 gap-3">
            {/* Centered layout thumbnail */}
            <button
              type="button"
              onClick={() => onChange({ layout: 'centered' })}
              className={`group flex flex-col items-center gap-2 rounded-lg border p-3 transition-all ${
                branding.layout === 'centered'
                  ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                  : 'border-input hover:border-foreground/20'
              }`}
            >
              <svg className="h-[60px] w-[100px]" viewBox="0 0 100 60" fill="none">
                <rect x="0" y="0" width="100" height="60" rx="4" className="fill-muted/50" />
                <rect x="28" y="10" width="44" height="40" rx="3" className="fill-background stroke-border" strokeWidth="1" />
                <rect x="34" y="18" width="32" height="3" rx="1" className="fill-muted-foreground/30" />
                <rect x="34" y="25" width="32" height="3" rx="1" className="fill-muted-foreground/20" />
                <rect x="34" y="32" width="32" height="3" rx="1" className="fill-muted-foreground/20" />
                <rect x="34" y="39" width="20" height="5" rx="1.5" className="fill-primary/40" />
              </svg>
              <span className="text-xs font-medium text-muted-foreground group-hover:text-foreground">{__('Centered', 'wp-sms')}</span>
            </button>

            {/* Split layout thumbnail */}
            <button
              type="button"
              onClick={() => onChange({ layout: 'split' })}
              className={`group flex flex-col items-center gap-2 rounded-lg border p-3 transition-all ${
                branding.layout === 'split'
                  ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                  : 'border-input hover:border-foreground/20'
              }`}
            >
              <svg className="h-[60px] w-[100px]" viewBox="0 0 100 60" fill="none">
                <rect x="0" y="0" width="100" height="60" rx="4" className="fill-background stroke-border" strokeWidth="1" />
                <rect x="0" y="0" width="45" height="60" rx="4" className="fill-primary/20" />
                <rect x="10" y="20" width="25" height="3" rx="1" className="fill-primary/40" />
                <rect x="10" y="27" width="18" height="2" rx="1" className="fill-primary/25" />
                <rect x="55" y="15" width="35" height="3" rx="1" className="fill-muted-foreground/30" />
                <rect x="55" y="22" width="35" height="3" rx="1" className="fill-muted-foreground/20" />
                <rect x="55" y="29" width="35" height="3" rx="1" className="fill-muted-foreground/20" />
                <rect x="55" y="36" width="22" height="5" rx="1.5" className="fill-primary/40" />
              </svg>
              <span className="text-xs font-medium text-muted-foreground group-hover:text-foreground">{__('Split', 'wp-sms')}</span>
            </button>
          </div>
        </Field>

        <Field>
          <FieldLabel>Corner Style</FieldLabel>
          <SegmentedGroup
            value={branding.border_radius}
            onChange={(v) => onChange({ border_radius: v })}
            options={RADIUS_OPTIONS}
            size="labeled"
          />
        </Field>

        <Field>
          <FieldLabel>Button Style</FieldLabel>
          <SegmentedGroup
            value={branding.button_style}
            onChange={(v) => onChange({ button_style: v })}
            options={BUTTON_STYLE_OPTIONS}
            size="labeled"
          />
        </Field>

        <Field>
          <FieldLabel>Social Buttons Layout</FieldLabel>
          <SegmentedGroup
            value={branding.social_position}
            onChange={(v) => onChange({ social_position: v })}
            options={SOCIAL_POSITIONS}
            size="labeled"
          />
        </Field>
      </CardContent>
    </Card>
  );
}
