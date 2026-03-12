import { useState, useCallback } from 'react';
import { AppShell, getParentSection } from '@/components/layout/app-shell';
import { SaveBar } from '@/components/layout/save-bar';
import { useSettings } from '@/hooks/use-settings';
import { getConfig } from '@/lib/api';
import { AuthenticationPage } from '@/pages/authentication';
import { SecurityPage } from '@/pages/security';
import { BrandingPage } from '@/pages/branding';
import { LogsPage } from '@/pages/logs';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import { AlertCircle } from 'lucide-react';

const { roles, version } = getConfig();

export default function App() {
  const [section, setSection] = useState('login-methods');
  const { settings, updateSetting, isDirty, saveStatus, save, loading, error } = useSettings();
  const handleSave = useCallback(() => { void save(); }, [save]);

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
      case 'authentication':
        return <AuthenticationPage section={section} settings={settings} onUpdate={updateSetting} />;
      case 'security':
        return <SecurityPage section={section} settings={settings} onUpdate={updateSetting} roles={roles} />;
      case 'branding':
        return <BrandingPage settings={settings} onUpdate={updateSetting} />;
      case 'logs':
        return <LogsPage settings={settings} onUpdate={updateSetting} />;
      default:
        return null;
    }
  }

  return (
    <div className="wsms-app">
      <div className="border border-border overflow-hidden">
        <AppShell activeSection={section} onNavigate={setSection} version={version}>
          {renderContent()}
        </AppShell>
      </div>

      <SaveBar isDirty={isDirty} saveStatus={saveStatus} onSave={handleSave} />
      <Toaster richColors position="bottom-right" />
    </div>
  );
}
