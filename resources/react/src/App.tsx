import { useCallback, useMemo } from 'react';
import { useIsRtl } from '@/hooks/use-is-rtl';
import { AppShell, getParentSection, VALID_SECTIONS } from '@/components/layout/app-shell';
import { SaveBarProvider } from '@/contexts/save-bar-context';
import { useSettings } from '@/hooks/use-settings';
import { useHashSection } from '@/hooks/use-hash-section';
import { getConfig } from '@/lib/api';
import { Campaigns } from '@/pages/messaging/campaigns';
import { Flows } from '@/pages/messaging/flows';
import { Contacts } from '@/pages/messaging/contacts';
import { Gateways } from '@/pages/messaging/gateways';
import { Webhooks } from '@/pages/messaging/webhooks';
import { IntegrationsPage } from '@/pages/messaging/apps';
import { MessagingButtonPage } from '@/pages/messaging-button';
import { BrandingAreaPage } from '@/pages/branding/branding-area-page';
import { Channels } from '@/pages/authentication/channels';
import { ProfileFields } from '@/pages/authentication/profile-fields';
import { RegistrationForms } from '@/pages/authentication/registration-forms';
import { Templates } from '@/pages/authentication/templates';
import { SecurityPage } from '@/pages/security';
import { PrivacyPage } from '@/pages/privacy';
import { MonitoringPage } from '@/pages/monitoring';
import { SettingsPage } from '@/pages/settings';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { DirectionProvider } from '@/components/ui/direction';
import { ConfirmProvider } from '@/components/confirm-provider';
import { AlertCircle } from 'lucide-react';

const DEFAULT_SECTION = 'campaigns';
const { roles, version } = getConfig();

export default function App() {
  const [section, setSection, subTab] = useHashSection(DEFAULT_SECTION, VALID_SECTIONS);
  const { settings, updateSetting, isDirty, saveStatus, save, loading, error } = useSettings();
  const isRtl = useIsRtl();
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

    const parent = getParentSection(section);

    switch (parent) {
      // Messaging
      case 'campaigns':
        return <Campaigns />;
      case 'flows':
        return <Flows />;
      case 'contacts':
        return <Contacts subTab={subTab} onNavigate={setSection} />;
      case 'messaging-button':
        return <MessagingButtonPage section={section} />;

      // Authentication
      case 'channels':
        return <Channels settings={settings} onUpdate={updateSetting} />;
      case 'registration-forms':
        return <RegistrationForms />;
      case 'profile-fields':
        return <ProfileFields settings={settings} onUpdate={updateSetting} />;
      case 'templates':
        return <Templates />;

      // Platform
      case 'gateways':
        return <Gateways />;
      case 'integrations':
        return <IntegrationsPage settings={settings} onUpdate={updateSetting} />;
      case 'webhooks':
        return <Webhooks />;
      case 'branding':
        return <BrandingAreaPage />;
      case 'monitoring':
        return <MonitoringPage subTab={subTab} onNavigate={setSection} />;
      case 'settings':
        switch (section) {
          case 's-security':
            return <SecurityPage subTab={subTab} onNavigate={setSection} settings={settings} onUpdate={updateSetting} roles={roles} />;
          case 's-privacy':
            return <PrivacyPage />;
          default:
            return <SettingsPage subTab={subTab} onNavigate={setSection} settings={settings} onUpdate={updateSetting} />;
        }

      default:
        return null;
    }
  }

  return (
    <DirectionProvider>
      <TooltipProvider>
        <ConfirmProvider>
          <SaveBarProvider defaultState={defaultSaveBarState}>
            <div className="wsms-app">
              <div className="border border-border">
                <AppShell activeSection={section} onNavigate={setSection} version={version}>
                  {renderContent()}
                </AppShell>
              </div>

              <Toaster richColors position={isRtl ? "bottom-left" : "bottom-right"} />
            </div>
          </SaveBarProvider>
        </ConfirmProvider>
      </TooltipProvider>
    </DirectionProvider>
  );
}
