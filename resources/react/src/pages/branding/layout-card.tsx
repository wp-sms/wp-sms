import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SegmentedGroup } from '@/components/ui/segmented-group';
import { Layout, SquareDashedBottom, PanelLeft } from 'lucide-react';
import type { BrandingSettings, BrandingLayout, ButtonStyle, SocialPosition } from '@/lib/api';

const LAYOUT_OPTIONS = [
  {
    value: 'centered' as BrandingLayout,
    label: 'Centered',
    icon: <SquareDashedBottom className="h-4 w-4" />,
  },
  {
    value: 'split' as BrandingLayout,
    label: 'Split',
    icon: <PanelLeft className="h-4 w-4" />,
  },
];

const RADIUS_OPTIONS = [
  { value: 0, label: 'Sharp', icon: <div className="h-4 w-4 border-2 border-current" style={{ borderRadius: 0 }} /> },
  { value: 8, label: 'Rounded', icon: <div className="h-4 w-4 border-2 border-current" style={{ borderRadius: '6px' }} /> },
  { value: 16, label: 'Pill', icon: <div className="h-4 w-4 border-2 border-current" style={{ borderRadius: '10px' }} /> },
];

const BUTTON_STYLE_OPTIONS = [
  {
    value: 'filled' as ButtonStyle,
    label: 'Filled',
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" stroke="none">
        <rect x="2" y="5" width="12" height="6" rx="2" />
      </svg>
    ),
  },
  {
    value: 'outline' as ButtonStyle,
    label: 'Outline',
    icon: (
      <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5">
        <rect x="2" y="5" width="12" height="6" rx="2" />
      </svg>
    ),
  },
  {
    value: 'ghost' as ButtonStyle,
    label: 'Ghost',
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
    label: 'Top',
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
    label: 'Bottom',
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
          Choose a page layout, corner style, and social button placement
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="space-y-2">
          <span className="text-sm font-medium">Page Layout</span>
          <SegmentedGroup
            value={branding.layout}
            onChange={(v) => onChange({ layout: v })}
            options={LAYOUT_OPTIONS}
            size="labeled"
          />
        </div>

        <div className="space-y-2">
          <span className="text-sm font-medium">Corner Style</span>
          <SegmentedGroup
            value={branding.border_radius}
            onChange={(v) => onChange({ border_radius: v })}
            options={RADIUS_OPTIONS}
            size="labeled"
          />
        </div>

        <div className="space-y-2">
          <span className="text-sm font-medium">Button Style</span>
          <SegmentedGroup
            value={branding.button_style}
            onChange={(v) => onChange({ button_style: v })}
            options={BUTTON_STYLE_OPTIONS}
            size="labeled"
          />
        </div>

        <div className="space-y-2">
          <span className="text-sm font-medium">Social Buttons Layout</span>
          <SegmentedGroup
            value={branding.social_position}
            onChange={(v) => onChange({ social_position: v })}
            options={SOCIAL_POSITIONS}
            size="labeled"
          />
        </div>
      </CardContent>
    </Card>
  );
}
