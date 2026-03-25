import { useCallback, useMemo } from 'react';
import { AppShell, getParentSection } from '@/components/layout/app-shell';
import { SaveBarProvider } from '@/contexts/save-bar-context';
import { useSettings } from '@/hooks/use-settings';
import { useHashSection } from '@/hooks/use-hash-section';
import { getConfig } from '@/lib/api';
import { getNavItemsForArea, getDefaultSection, getValidSections } from '@/lib/area-nav';
import { MessagingPage } from '@/pages/messaging';
import { MessagingButtonPage } from '@/pages/messaging-button';
import { AuthenticationPage } from '@/pages/authentication';
import { SecurityPage } from '@/pages/security';
import { IntegrationsPage } from '@/pages/integrations';
import { BrandingPage } from '@/pages/branding';
import { GeneralPage } from '@/pages/general';
import { LogsPage } from '@/pages/logs';
import { ReportsPage } from '@/pages/reports';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ConfirmProvider } from '@/components/confirm-provider';
import { AlertCircle } from 'lucide-react';

const { roles, version, area } = getConfig();
const navItems = getNavItemsForArea(area);
const defaultSection = getDefaultSection(area);
const validSections = getValidSections(navItems);

export default function App() {
  const [section, setSection, subTab] = useHashSection(defaultSection, validSections);
  const { settings, updateSetting, isDirty, saveStatus, save, loading, error } = useSettings();
  const handleSave = useCallback(() => { void save(); }, [save]);
  const defaultSaveBarState = useMemo(
    () => ({ isDirty, saveStatus, onSave: handleSave }),
    [isDirty, saveStatus, handleSave],
  );

  function renderContent() {
    if (loading) {
      return (
        <div className="space-y-4">
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-32 w-full" />
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

    const parent = getParentSection(section, navItems);

    switch (parent) {
      case 'campaigns':
      case 'flows':
      case 'contacts':
      case 'gateways':
      case 'webhooks':
      case 'message-logs':
      case 'subscription-forms':
      case 'settings':
        return <MessagingPage section={section} subTab={subTab} onNavigate={setSection} />;
      case 'apps':
        return <MessagingPage section={section} subTab={subTab} onNavigate={setSection} settings={settings} onUpdate={updateSetting} />;
      case 'messaging-button':
        return <MessagingButtonPage section={section} />;
      case 'authentication':
        return <AuthenticationPage section={section} settings={settings} onUpdate={updateSetting} />;
      case 'security':
        return <SecurityPage section={section} settings={settings} onUpdate={updateSetting} roles={roles} />;
      case 'integrations':
        return <IntegrationsPage section={section} settings={settings} onUpdate={updateSetting} />;
      case 'general':
        return <GeneralPage settings={settings} onUpdate={updateSetting} />;
      case 'branding':
        return <BrandingPage settings={settings} onUpdate={updateSetting} />;
      case 'monitoring':
        if (section === 'reports') return <ReportsPage />;
        return <LogsPage />;
      default:
        return null;
    }
  }

  return (
    <TooltipProvider>
      <ConfirmProvider>
        <SaveBarProvider defaultState={defaultSaveBarState}>
          <div className="wsms-app">
            <div className="border border-border">
              <AppShell activeSection={section} onNavigate={setSection} version={version} area={area} navItems={navItems}>
                {renderContent()}
              </AppShell>
            </div>

            <Toaster richColors position="bottom-right" />
          </div>
        </SaveBarProvider>
      </ConfirmProvider>
    </TooltipProvider>
  );
}
