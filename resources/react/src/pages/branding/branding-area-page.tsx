import { useCallback } from 'react';
import { useBrandingSettings } from '@/hooks/use-branding-settings';
import { useSaveBar } from '@/contexts/save-bar-context';
import { BrandingPage } from './index';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-react';

export function BrandingAreaPage() {
  const branding = useBrandingSettings();
  const handleSave = useCallback(() => { void branding.save(); }, [branding.save]);
  useSaveBar({ isDirty: branding.isDirty, saveStatus: branding.saveStatus, onSave: handleSave });

  if (branding.loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  if (branding.error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{branding.error}</AlertDescription>
      </Alert>
    );
  }

  return (
    <BrandingPage
      branding={branding.settings}
      onChange={(patch) => {
        for (const [k, v] of Object.entries(patch)) {
          branding.updateSetting(k, v);
        }
      }}
      authBaseUrl={branding.authBaseUrl}
      authPagesActive={branding.authPagesActive}
    />
  );
}
