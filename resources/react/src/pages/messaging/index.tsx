import { Flows } from './flows';
import { Contacts } from './contacts';
import { Gateways } from './gateways';
import { MessageLogs } from './message-logs';

interface MessagingPageProps {
  section: string;
}

export function MessagingPage({ section }: MessagingPageProps) {
  switch (section) {
    case 'contacts':
      return <Contacts />;
    case 'gateways':
      return <Gateways />;
    case 'message-logs':
      return <MessageLogs />;
    default:
      return <Flows />;
  }
}
