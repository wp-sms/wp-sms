import { Flows } from './flows';
import { Contacts } from './contacts';
import { Gateways } from './gateways';
import { Apps } from './apps';
import { Webhooks } from './webhooks';
import { MessageLogs } from './message-logs';
import { Campaigns } from './campaigns';
import { OptOutSettings } from './opt-out-settings';
import { PhoneRestriction } from './phone-restriction';
import { SubscriptionForms } from './subscription-forms';
import { SystemHealth } from '@/pages/system';
import type { AuthSettings } from '@/lib/api';

interface MessagingPageProps {
  section: string;
  subTab?: string;
  onNavigate?: (s: string) => void;
  settings?: Required<AuthSettings>;
  onUpdate?: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function MessagingPage({ section, subTab, onNavigate, settings, onUpdate }: MessagingPageProps) {
  switch (section) {
    case 'campaigns':
      return <Campaigns />;
    case 'contacts':
      return <Contacts subTab={subTab} onNavigate={onNavigate} />;
    case 'gateways':
      return <Gateways />;
    case 'apps':
      return <Apps settings={settings} onUpdate={onUpdate} />;
    case 'webhooks':
      return <Webhooks />;
    case 'message-logs':
      return <MessageLogs />;
    case 'opt-out':
      return <OptOutSettings />;
    case 'phone-restriction':
      return <PhoneRestriction />;
    case 'subscription-forms':
      return <SubscriptionForms />;
    case 'system':
      return <SystemHealth />;
    default:
      return <Flows />;
  }
}
