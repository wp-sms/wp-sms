import { Channels } from './channels';
import { Registration } from './registration';
import type { AuthSettings } from '@/lib/api';

interface AuthenticationPageProps {
  section: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function AuthenticationPage({ section, settings, onUpdate }: AuthenticationPageProps) {
  if (section === 'registration') {
    return <Registration settings={settings} onUpdate={onUpdate} />;
  }

  return <Channels settings={settings} onUpdate={onUpdate} />;
}
