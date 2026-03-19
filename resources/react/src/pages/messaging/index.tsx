import { Flows } from './flows';
import { Contacts } from './contacts';
import { Gateways } from './gateways';
import { Apps } from './apps';
import { MessageLogs } from './message-logs';
import { Campaigns } from './campaigns';
import { OptOutSettings } from './opt-out-settings';

interface MessagingPageProps {
  section: string;
  subTab?: string;
  onNavigate?: (s: string) => void;
}

export function MessagingPage({ section, subTab, onNavigate }: MessagingPageProps) {
  switch (section) {
    case 'campaigns':
      return <Campaigns />;
    case 'contacts':
      return <Contacts subTab={subTab} onNavigate={onNavigate} />;
    case 'gateways':
      return <Gateways />;
    case 'apps':
      return <Apps />;
    case 'message-logs':
      return <MessageLogs />;
    case 'opt-out':
      return <OptOutSettings />;
    default:
      return <Flows />;
  }
}
