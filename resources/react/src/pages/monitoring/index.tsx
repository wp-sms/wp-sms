import { __ } from '@wordpress/i18n';
import { useState, type ReactNode } from 'react';
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
  const [headerMeta, setHeaderMeta] = useState<ReactNode>(null);
  const [headerActions, setHeaderActions] = useState<ReactNode>(null);

  return (
    <Tabs value={activeTab} onValueChange={handleTabChange}>
      <PageHeader icon={BarChart3} title={__('Monitoring', 'wp-sms')} metadata={headerMeta} actions={headerActions}>
        <TabsList variant="line" className="mt-3">
          <TabsTrigger value="health">{__('Health', 'wp-sms')}</TabsTrigger>
          <TabsTrigger value="logs">{__('Auth Logs', 'wp-sms')}</TabsTrigger>
          <TabsTrigger value="messages">{__('Message Logs', 'wp-sms')}</TabsTrigger>
          <TabsTrigger value="reports">{__('Reports', 'wp-sms')}</TabsTrigger>
        </TabsList>
      </PageHeader>

      <TabsContent value="health">
        <SystemHealth embedded setHeaderMeta={setHeaderMeta} setHeaderActions={setHeaderActions} />
      </TabsContent>

      <TabsContent value="logs">
        <LogsPage embedded setHeaderMeta={setHeaderMeta} setHeaderActions={setHeaderActions} />
      </TabsContent>

      <TabsContent value="messages">
        {activeTab === 'messages' && <MessageLogs embedded setHeaderMeta={setHeaderMeta} setHeaderActions={setHeaderActions} />}
      </TabsContent>

      <TabsContent value="reports">
        <ReportsPage embedded />
      </TabsContent>
    </Tabs>
  );
}
