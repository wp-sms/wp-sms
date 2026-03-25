import { useCallback } from 'react';
import { useMessagingButtonSettings } from './use-mb-settings';
import { AppearancePage } from './appearance';
import { PagesConfigPage } from './pages-config';
import { TeamPage } from './team';
import { DisplayRulesPage } from './display-rules';
import { WidgetPreview } from './widget-preview';
import { SaveBar } from '@/components/layout/save-bar';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, MessageSquare } from 'lucide-react';
import { PageHeader } from '@/components/layout/page-header';

interface MessagingButtonPageProps {
  section: string;
}

export function MessagingButtonPage({ section }: MessagingButtonPageProps) {
  const { settings, updateSettings, isDirty, saveStatus, save, loading, error, rawResponse } = useMessagingButtonSettings();
  const wpTimezone = rawResponse?.wp_timezone;
  const handleSave = useCallback(() => { void save(); }, [save]);

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
      <PageHeader icon={MessageSquare} title="Messaging Button" />
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
      <SaveBar isDirty={isDirty} saveStatus={saveStatus} onSave={handleSave} />
    </>
  );
}
