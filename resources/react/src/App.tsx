import { useCallback, useEffect, useMemo } from 'react';
import { useIsRtl } from '@/hooks/use-is-rtl';
import { AppShell, SECTION_IDS, VALID_SECTIONS } from '@/components/layout/app-shell';
import { SaveBarProvider } from '@/contexts/save-bar-context';
import { useSettings } from '@/hooks/use-settings';
import { useHashSection } from '@/hooks/use-hash-section';
import { useCapabilities } from '@/hooks/use-capabilities';
import { getConfig } from '@/lib/api';
import { shouldShowOnboarding } from '@/hooks/use-onboarding';
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
import { ExtensionsPage } from '@/pages/extensions';
import { OnboardingWizard } from '@/pages/onboarding';
import { AccessControlPage } from '@/pages/settings/access-control';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { DirectionProvider } from '@/components/ui/direction';
import { ConfirmProvider } from '@/components/confirm-provider';
import { AlertCircle } from 'lucide-react';

const DEFAULT_SECTION = 'dashboard';
const { roles, version } = getConfig();

/** Extend valid sections with onboarding (not in sidebar nav). */
const ALL_SECTIONS = new Set([...VALID_SECTIONS, 'onboarding']);


export default function App() {
  const [section, setSection, subTab] = useHashSection(DEFAULT_SECTION, ALL_SECTIONS);
  const { settings, updateSetting, isDirty, saveStatus, save, loading, error, socialProviders, mfaChannels } = useSettings();
  const isRtl = useIsRtl();
  const { caps, canViewSection } = useCapabilities();

  // Auto-redirect to onboarding when status is pending (once per session) — admin only.
  useEffect(() => {
    if (section === 'onboarding') return;
    if (!caps.is_admin) return;
    if (sessionStorage.getItem('wsms_onboarding_redirected')) return;
    if (shouldShowOnboarding()) {
      sessionStorage.setItem('wsms_onboarding_redirected', '1');
      setSection('onboarding');
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Redirect to first accessible section if current section is not accessible.
  useEffect(() => {
    if (section === 'onboarding') return;
    if (canViewSection(section)) return;

    const fallback = SECTION_IDS.find((s) => canViewSection(s));
    if (fallback) {
      setSection(fallback);
    }
  }, [section, canViewSection, setSection]);
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
            return <Channels settings={settings} onUpdate={updateSetting} socialProviders={socialProviders} mfaChannels={mfaChannels} />;
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
          case 'extensions':
            return <ExtensionsPage />;
          case 'migration':
            return <MigrationPage />;
          case 'access-control':
            return caps.is_admin ? <AccessControlPage /> : null;
          default:
            return <GeneralPage settings={settings} onUpdate={updateSetting} />;
        }

      default:
        return null;
    }
  }

  // Onboarding wizard bypasses the AppShell entirely.
  if (section === 'onboarding') {
    return (
      <DirectionProvider>
        <TooltipProvider>
          <ConfirmProvider>
            <div className="wsms-app">
              <div className="border border-border">
                <OnboardingWizard onComplete={() => setSection('dashboard')} onNavigate={setSection} />
              </div>
              <Toaster richColors position={isRtl ? "bottom-left" : "bottom-right"} toastOptions={{ duration: 5000 }} />
            </div>
          </ConfirmProvider>
        </TooltipProvider>
      </DirectionProvider>
    );
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
