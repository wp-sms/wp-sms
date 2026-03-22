import { Channels } from './channels';
import { ProfileFields } from './profile-fields';
import { RegistrationForms } from './registration-forms';
import { Templates } from './templates';
import type { AuthSettings } from '@/lib/api';

interface AuthenticationPageProps {
  section: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function AuthenticationPage({ section, settings, onUpdate }: AuthenticationPageProps) {
  if (section === 'profile-fields') {
    return <ProfileFields settings={settings} onUpdate={onUpdate} />;
  }

  if (section === 'registration-forms') {
    return <RegistrationForms />;
  }

  if (section === 'templates') {
    return <Templates />;
  }

  return <Channels settings={settings} onUpdate={onUpdate} />;
}
