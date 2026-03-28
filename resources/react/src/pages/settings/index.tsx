import { __ } from '@wordpress/i18n';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageHeader } from '@/components/layout/page-header';
import { Settings2 } from 'lucide-react';
import { useSubTabs } from '@/hooks/use-sub-tabs';
import { GeneralPage } from '@/pages/general';
import { OptOutSettings } from '@/pages/messaging/opt-out-settings';
import type { AuthSettings } from '@/lib/api';

const TABS = ['general', 'opt-out'] as const;

interface SettingsPageProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function SettingsPage({ subTab, onNavigate, settings, onUpdate }: SettingsPageProps) {
  const [activeTab, handleTabChange] = useSubTabs('s-general', TABS, subTab, onNavigate);

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader icon={Settings2} title={__('Settings', 'wp-sms')}>
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="general">{__('General', 'wp-sms')}</TabsTrigger>
          <TabsTrigger value="opt-out">{__('Opt-Out', 'wp-sms')}</TabsTrigger>
        </TabsList>
      </PageHeader>

      <TabsContent value="general">
        <GeneralPage settings={settings} onUpdate={onUpdate} embedded />
      </TabsContent>

      <TabsContent value="opt-out">
        {activeTab === 'opt-out' && <OptOutSettings embedded />}
      </TabsContent>
    </Tabs>
  );
}
