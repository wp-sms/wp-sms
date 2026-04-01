import { useCallback, useMemo } from 'react';
import { useIsRtl } from '@/hooks/use-is-rtl';
import { AppShell, VALID_SECTIONS } from '@/components/layout/app-shell';
import { SaveBarProvider } from '@/contexts/save-bar-context';
import { useSettings } from '@/hooks/use-settings';
import { useHashSection } from '@/hooks/use-hash-section';
import { getConfig } from '@/lib/api';
import { DashboardPage } from '@/pages/dashboard';
import { Contacts } from '@/pages/messaging/contacts';
import { Campaigns } from '@/pages/messaging/campaigns';
import { Flows } from '@/pages/messaging/flows';
import { Gateways } from '@/pages/messaging/gateways';
import { Webhooks } from '@/pages/messaging/webhooks';
import { IntegrationsPage } from '@/pages/messaging/apps';
import { MessagingButtonPage } from '@/pages/messaging-button';
import { Channels } from '@/pages/authentication/channels';
import { RegistrationForms } from '@/pages/authentication/registration-forms';
import { ProfileFields } from '@/pages/authentication/profile-fields';
import { Templates } from '@/pages/authentication/templates';
import { SystemHealth } from '@/pages/system';
import { LogsPage } from '@/pages/logs';
import { MessageLogs } from '@/pages/messaging/message-logs';
import { ReportsPage } from '@/pages/reports';
import { GeneralPage } from '@/pages/general';
import { SecurityPage } from '@/pages/security';
import { PrivacyPage } from '@/pages/privacy';
import { OptOutSettings } from '@/pages/messaging/opt-out-settings';
import { BrandingAreaPage } from '@/pages/branding/branding-area-page';
import { MigrationPage } from '@/pages/migration';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { DirectionProvider } from '@/components/ui/direction';
import { ConfirmProvider } from '@/components/confirm-provider';
import { AlertCircle } from 'lucide-react';

const DEFAULT_SECTION = 'dashboard';
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

    switch (section) {
      case 'dashboard':
        return <DashboardPage onNavigate={setSection} />;

      case 'campaigns':
        return <Campaigns />;

      case 'automation':
        return <Flows />;

      case 'audience':
        return <Contacts subTab={subTab || 'contacts'} />;

      case 'channels':
        switch (subTab) {
          case 'integrations':
            return <IntegrationsPage settings={settings} onUpdate={updateSetting} />;
          case 'webhooks':
            return <Webhooks />;
          case 'messaging-button':
            return <MessagingButtonPage />;
          default:
            return <Gateways />;
        }

      case 'identity':
        switch (subTab) {
          case 'registration-forms':
            return <RegistrationForms authEnabled={!!settings.auth_enabled} />;
          case 'profile-fields':
            return <ProfileFields settings={settings} onUpdate={updateSetting} />;
          case 'templates':
            return <Templates authEnabled={!!settings.auth_enabled} />;
          default:
            return <Channels settings={settings} onUpdate={updateSetting} />;
        }

      case 'monitoring':
        switch (subTab) {
          case 'auth-logs':
            return <LogsPage />;
          case 'message-logs':
            return <MessageLogs />;
          case 'reports':
            return <ReportsPage />;
          default:
            return <SystemHealth />;
        }

      case 'settings':
        switch (subTab) {
          case 'security':
            return <SecurityPage settings={settings} onUpdate={updateSetting} roles={roles} />;
          case 'privacy':
            return <PrivacyPage />;
          case 'compliance':
            return <OptOutSettings />;
          case 'branding':
            return <BrandingAreaPage />;
          case 'migration':
            return <MigrationPage />;
          default:
            return <GeneralPage settings={settings} onUpdate={updateSetting} />;
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
                <AppShell activeSection={section} activeSubTab={subTab} onNavigate={setSection} version={version}>
                  {renderContent()}
                </AppShell>
              </div>

              <Toaster richColors position={isRtl ? "bottom-left" : "bottom-right"} toastOptions={{ duration: 5000 }} />
            </div>
          </SaveBarProvider>
        </ConfirmProvider>
      </TooltipProvider>
    </DirectionProvider>
  );
}
