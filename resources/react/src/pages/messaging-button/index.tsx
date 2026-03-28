import { __ } from '@wordpress/i18n';
import { useCallback, useState } from 'react';
import { useMessagingButtonSettings } from './use-mb-settings';
import { AppearancePage } from './appearance';
import { PagesConfigPage } from './pages-config';
import { TeamPage } from './team';
import { DisplayRulesPage } from './display-rules';
import { WidgetPreview } from './widget-preview';
import { useSaveBar } from '@/contexts/save-bar-context';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Eye, MessageSquare } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';

interface MessagingButtonPageProps {
  section: string;
}

export function MessagingButtonPage({ section }: MessagingButtonPageProps) {
  const { settings, updateSettings, isDirty, saveStatus, save, loading, error, rawResponse } = useMessagingButtonSettings();
  const wpTimezone = rawResponse?.wp_timezone;
  const handleSave = useCallback(() => { void save(); }, [save]);
  useSaveBar({ isDirty, saveStatus, onSave: handleSave });
  const [drawerOpen, setDrawerOpen] = useState(false);

  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  const renderSection = () => {
    switch (section) {
      case 'mb-pages':
        return <PagesConfigPage settings={settings} onUpdate={updateSettings} />;
      case 'mb-team':
        return <TeamPage settings={settings} onUpdate={updateSettings} />;
      case 'mb-display-rules':
        return <DisplayRulesPage settings={settings} wpTimezone={wpTimezone} onUpdate={updateSettings} />;
      default:
        return <AppearancePage settings={settings} onUpdate={updateSettings} />;
    }
  };

  return (
    <>
      <PageHeader
        icon={MessageSquare}
        title={__('Messaging Button', 'wp-sms')}
        metadata={
          <Badge variant={settings.enabled ? 'success' : 'neutral'} dot>
            {settings.enabled ? 'Active' : 'Inactive'}
          </Badge>
        }
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
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              Enable
              <Switch
                checked={settings.enabled}
                onCheckedChange={(checked) => updateSettings('enabled', checked)}
                aria-label="Toggle widget"
              />
            </label>
          </>
        }
      />
      <div className="mt-4 flex gap-6">
        <div className="min-w-0 flex-1">
          {renderSection()}
        </div>
        <div className="hidden xl:block w-[320px] flex-shrink-0">
          <div className="sticky top-20">
            <WidgetPreview settings={settings} />
          </div>
        </div>
      </div>
      {/* Mobile preview drawer */}
      <Drawer open={drawerOpen} onOpenChange={setDrawerOpen}>
        <DrawerContent className="sm:max-w-md">
          <DrawerHeader>
            <DrawerTitle>{__('Preview', 'wp-sms')}</DrawerTitle>
          </DrawerHeader>
          <div className="overflow-y-auto p-4">
            <WidgetPreview settings={settings} />
          </div>
        </DrawerContent>
      </Drawer>
    </>
  );
}
