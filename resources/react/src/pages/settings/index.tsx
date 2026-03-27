import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageHeader } from '@/components/layout/page-header';
import { Settings2 } from 'lucide-react';
import { useSubTabs } from '@/hooks/use-sub-tabs';
import { GeneralPage } from '@/pages/general';
import { OptOutSettings } from '@/pages/messaging/opt-out-settings';
import { PhoneRestriction } from '@/pages/messaging/phone-restriction';
import type { AuthSettings } from '@/lib/api';

const TABS = ['general', 'opt-out', 'phone-restrictions'] as const;

interface SettingsPageProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function SettingsPage({ subTab, onNavigate, settings, onUpdate }: SettingsPageProps) {
  const [activeTab, handleTabChange] = useSubTabs('settings', TABS, subTab, onNavigate);

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader icon={Settings2} title="Settings">
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="general">General</TabsTrigger>
          <TabsTrigger value="opt-out">Opt-Out</TabsTrigger>
          <TabsTrigger value="phone-restrictions">Phone Restrictions</TabsTrigger>
        </TabsList>
      </PageHeader>

      <TabsContent value="general">
        <GeneralPage settings={settings} onUpdate={onUpdate} embedded />
      </TabsContent>

      <TabsContent value="opt-out">
        {activeTab === 'opt-out' && <OptOutSettings embedded />}
      </TabsContent>

      <TabsContent value="phone-restrictions">
        {activeTab === 'phone-restrictions' && <PhoneRestriction embedded />}
      </TabsContent>
    </Tabs>
  );
}
