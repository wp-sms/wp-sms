import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { RotateCcw, Eye, EyeOff, Paintbrush } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { useConfirm } from '@/components/confirm-provider';
import { DEFAULTS } from '@/lib/constants';
import { LogoCard } from './logo-card';
import { ColorsCard } from './colors-card';
import { LayoutCard } from './layout-card';
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
  const [drawerOpen, setDrawerOpen] = useState(false);
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
    <>
      <PageHeader
        icon={Paintbrush}
        title="Branding"
        actions={
          <>
            <Button
              variant="outline"
              size="sm"
              className="xl:hidden"
              onClick={() => setDrawerOpen(true)}
            >
              <Eye className="mr-1.5 h-3.5 w-3.5" />
              Preview
            </Button>
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
              Reset
            </Button>
          </>
        }
      />
      <div className="mt-4 flex gap-6">
      {/* Settings panel */}
      <div className="min-w-0 flex-1 space-y-4">
        <LogoCard branding={branding} onChange={handleBrandingChange} />
        <ColorsCard branding={branding} onChange={handleBrandingChange} />
        <LayoutCard branding={branding} onChange={handleBrandingChange} />

        {!isCentered && (
          <>
            <SplitPanelCard branding={branding} onChange={handleBrandingChange} />
            <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
              Background image/color applies to the centered layout. In split layout, use the panel background above instead.
            </p>
          </>
        )}
      </div>

      {/* Live preview (desktop only, fixed width, collapsible) */}
      {previewVisible && (
        <div className="hidden xl:block sticky top-4 h-fit w-[400px] shrink-0">
          <BrandingPreview branding={branding} baseUrl={baseUrl} />
        </div>
      )}
    </div>

    {/* Mobile preview drawer */}
    <Drawer open={drawerOpen} onOpenChange={setDrawerOpen}>
      <DrawerContent className="sm:max-w-lg">
        <DrawerHeader>
          <DrawerTitle>Preview</DrawerTitle>
        </DrawerHeader>
        <div className="overflow-y-auto p-4">
          <BrandingPreview branding={branding} baseUrl={baseUrl} />
        </div>
      </DrawerContent>
    </Drawer>
    </>
  );
}
