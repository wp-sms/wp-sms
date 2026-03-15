import { WooCommerce } from './woocommerce';
import type { AuthSettings } from '@/lib/api';

interface IntegrationsPageProps {
  section: string;
  settings: Required<AuthSettings>;
  onUpdate: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
}

export function IntegrationsPage({ section, settings, onUpdate }: IntegrationsPageProps) {
  return <WooCommerce settings={settings} onUpdate={onUpdate} />;
}
