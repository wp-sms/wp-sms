import { __ } from '@wordpress/i18n';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { RotateCcw, Eye, EyeOff, Paintbrush } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';
import { useConfirm } from '@/components/confirm-provider';
import { BRANDING_DEFAULTS } from '@/hooks/use-branding-settings';
import { LogoCard } from './logo-card';
import { ColorsCard } from './colors-card';
import { LayoutCard } from './layout-card';
import { SplitPanelCard } from './split-panel-card';
import { BrandingPreview } from './branding-preview';
import type { BrandingSettings } from '@/lib/api';

interface BrandingPageProps {
  branding: BrandingSettings;
  onChange: (patch: Partial<BrandingSettings>) => void;
  authBaseUrl: string;
}

export function BrandingPage({ branding, onChange, authBaseUrl }: BrandingPageProps) {
  type BrandingTab = 'colors' | 'logo' | 'layout';
  const [activeTab, setActiveTab] = useState<BrandingTab>('colors');
  const [previewVisible, setPreviewVisible] = useState(true);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const confirm = useConfirm();

  const handleBrandingChange = useCallback(
    (patch: Partial<BrandingSettings>) => {
      onChange(patch);
    },
    [onChange]
  );

  const handleReset = useCallback(async () => {
    const ok = await confirm({
      title: __('Reset branding?', 'wp-sms'),
      description: __('All branding settings will be restored to their defaults.', 'wp-sms'),
      confirmLabel: __('Reset', 'wp-sms'),
      variant: 'destructive',
    });
    if (ok) {
      onChange(BRANDING_DEFAULTS);
    }
  }, [onChange, confirm]);

  const isCentered = branding.layout === 'centered';
  const baseUrl = authBaseUrl || '/account';

  return (
    <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as BrandingTab)}>
      <PageHeader
        icon={Paintbrush}
        title={__('Branding', 'wp-sms')}
        actions={
          <>
            <Button
              variant="outline"
              size="sm"
              className="xl:hidden"
              onClick={() => setDrawerOpen(true)}
            >
              <Eye className="me-1.5 h-3.5 w-3.5" />
              {__('Preview', 'wp-sms')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="hidden xl:inline-flex"
              onClick={() => setPreviewVisible((v) => !v)}
            >
              {previewVisible ? (
                <><EyeOff className="me-1.5 h-3.5 w-3.5" /> {__('Hide Preview', 'wp-sms')}</>
              ) : (
                <><Eye className="me-1.5 h-3.5 w-3.5" /> {__('Show Preview', 'wp-sms')}</>
              )}
            </Button>
            <Button variant="outline" size="sm" onClick={handleReset}>
              <RotateCcw className="me-1.5 h-3.5 w-3.5" />
              {__('Reset', 'wp-sms')}
            </Button>
          </>
        }
      >
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="colors">{__('Colors', 'wp-sms')}</TabsTrigger>
          <TabsTrigger value="logo">{__('Logo', 'wp-sms')}</TabsTrigger>
          <TabsTrigger value="layout">{__('Layout', 'wp-sms')}</TabsTrigger>
        </TabsList>
      </PageHeader>

      <div className="mt-4 flex gap-6">
        <div className="min-w-0 flex-1">
          <TabsContent value="colors">
            <ColorsCard branding={branding} onChange={handleBrandingChange} />
          </TabsContent>

          <TabsContent value="logo">
            <LogoCard branding={branding} onChange={handleBrandingChange} />
          </TabsContent>

          <TabsContent value="layout" className="space-y-4">
            <LayoutCard branding={branding} onChange={handleBrandingChange} />
            {!isCentered && (
              <>
                <SplitPanelCard branding={branding} onChange={handleBrandingChange} />
                <p className="text-xs text-muted-foreground rounded-md bg-muted/50 p-3">
                  {__('Background image/color applies to the centered layout. In split layout, use the panel background above instead.', 'wp-sms')}
                </p>
              </>
            )}
          </TabsContent>
        </div>

        {previewVisible && (
          <div className="hidden xl:block sticky top-4 h-fit w-[400px] shrink-0">
            <BrandingPreview branding={branding} baseUrl={baseUrl} />
          </div>
        )}
      </div>

      <Drawer open={drawerOpen} onOpenChange={setDrawerOpen}>
        <DrawerContent className="sm:max-w-lg">
          <DrawerHeader>
            <DrawerTitle>{__('Preview', 'wp-sms')}</DrawerTitle>
          </DrawerHeader>
          <div className="overflow-y-auto p-4">
            <BrandingPreview branding={branding} baseUrl={baseUrl} />
          </div>
        </DrawerContent>
      </Drawer>
    </Tabs>
  );
}
