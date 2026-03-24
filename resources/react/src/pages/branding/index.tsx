import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import { RotateCcw, Eye, EyeOff } from 'lucide-react';
import { useConfirm } from '@/components/confirm-provider';
import { DEFAULTS } from '@/lib/constants';
import { LogoCard } from './logo-card';
import { ColorsCard } from './colors-card';
import { LayoutCard } from './layout-card';
import { TypographyCard } from './typography-card';
import { SplitPanelCard } from './split-panel-card';
import { BrandingPreview } from './branding-preview';
import type { AuthSettings, BrandingSettings } from '@/lib/api';

interface BrandingPageProps {
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function BrandingPage({ settings, onUpdate }: BrandingPageProps) {
  const branding = settings.branding;
  const [previewVisible, setPreviewVisible] = useState(true);
  const confirm = useConfirm();

  const handleBrandingChange = useCallback(
    (patch: Partial<BrandingSettings>) => {
      onUpdate('branding', { ...branding, ...patch });
    },
    [branding, onUpdate]
  );

  const handleReset = useCallback(async () => {
    const ok = await confirm({
      title: 'Reset branding?',
      description: 'All branding settings will be restored to their defaults.',
      confirmLabel: 'Reset',
      variant: 'destructive',
    });
    if (ok) {
      onUpdate('branding', DEFAULTS.branding);
    }
  }, [onUpdate, confirm]);

  const isCentered = branding.layout === 'centered';
  const baseUrl = settings.auth_base_url || '/account';

  return (
    <div className="flex gap-6">
      {/* Settings panel */}
      <div className="min-w-0 flex-1 space-y-4">
        <LogoCard branding={branding} onChange={handleBrandingChange} />
        <ColorsCard branding={branding} onChange={handleBrandingChange} />
        <LayoutCard branding={branding} onChange={handleBrandingChange} />
        <TypographyCard branding={branding} onChange={handleBrandingChange} />

        {!isCentered && (
          <>
            <SplitPanelCard branding={branding} onChange={handleBrandingChange} />
            <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
              Background image/color applies to the centered layout. In split layout, use the panel background above instead.
            </p>
          </>
        )}

        <div className="flex items-center justify-between pt-2">
          <Button
            variant="outline"
            size="sm"
            className="hidden xl:inline-flex"
            onClick={() => setPreviewVisible((v) => !v)}
          >
            {previewVisible ? (
              <><EyeOff className="mr-1.5 h-3.5 w-3.5" /> Hide Preview</>
            ) : (
              <><Eye className="mr-1.5 h-3.5 w-3.5" /> Show Preview</>
            )}
          </Button>
          <Button variant="outline" size="sm" onClick={handleReset}>
            <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
            Reset Branding
          </Button>
        </div>
      </div>

      {/* Live preview (desktop only, fixed width, collapsible) */}
      {previewVisible && (
        <div className="hidden xl:block sticky top-4 h-fit w-[400px] shrink-0">
          <BrandingPreview branding={branding} baseUrl={baseUrl} />
        </div>
      )}
    </div>
  );
}
