import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageHeader } from '@/components/layout/page-header';
import { BarChart3 } from 'lucide-react';
import { useSubTabs } from '@/hooks/use-sub-tabs';
import { SystemHealth } from '@/pages/system';
import { LogsPage } from '@/pages/logs';
import { MessageLogs } from '@/pages/messaging/message-logs';
import { ReportsPage } from '@/pages/reports';

const TABS = ['health', 'logs', 'messages', 'reports'] as const;

interface MonitoringPageProps {
  subTab?: string;
  onNavigate?: (s: string) => void;
}

export function MonitoringPage({ subTab, onNavigate }: MonitoringPageProps) {
  const [activeTab, handleTabChange] = useSubTabs('monitoring', TABS, subTab, onNavigate);

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader icon={BarChart3} title="Monitoring">
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="health">Health</TabsTrigger>
          <TabsTrigger value="logs">Auth Logs</TabsTrigger>
          <TabsTrigger value="messages">Message Logs</TabsTrigger>
          <TabsTrigger value="reports">Reports</TabsTrigger>
        </TabsList>
      </PageHeader>

      <TabsContent value="health">
        <SystemHealth embedded />
      </TabsContent>

      <TabsContent value="logs">
        <LogsPage embedded />
      </TabsContent>

      <TabsContent value="messages">
        {activeTab === 'messages' && <MessageLogs embedded />}
      </TabsContent>

      <TabsContent value="reports">
        <ReportsPage embedded />
      </TabsContent>
    </Tabs>
  );
}
