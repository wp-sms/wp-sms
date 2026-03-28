import { __ } from '@wordpress/i18n';
import { PageSection } from '@/components/ui/page-section';
import { EmptyState } from '@/components/ui/empty-state';
import { Zap, Play } from 'lucide-react';

interface AutomationTabProps {
  triggers: Array<{ id: string; name: string; description: string }>;
  actions: Array<{ id: string; name: string; description: string }>;
}

export function IntegrationAutomationTab({ triggers, actions }: AutomationTabProps) {
  if (triggers.length === 0 && actions.length === 0) {
    return <EmptyState icon={Zap} title={__('No triggers or actions yet', 'wp-sms')} compact />;
  }

  const hasBoth = triggers.length > 0 && actions.length > 0;

  return (
    <div className="space-y-6">
      <div className={hasBoth ? 'grid grid-cols-1 lg:grid-cols-2 gap-6' : ''}>
        {triggers.length > 0 && (
          <PageSection icon={Zap} title={`Triggers (${triggers.length})`}>
            <div className="space-y-2">
              {triggers.map((item) => (
                <div key={item.id} className="rounded-md border border-l-2 border-l-amber-400 p-3">
                  <div className="text-sm font-medium">{item.name}</div>
                  <div className="text-xs text-muted-foreground mt-0.5">{item.description}</div>
                </div>
              ))}
            </div>
          </PageSection>
        )}

        {actions.length > 0 && (
          <PageSection icon={Play} title={`Actions (${actions.length})`}>
            <div className="space-y-2">
              {actions.map((item) => (
                <div key={item.id} className="rounded-md border border-l-2 border-l-blue-400 p-3">
                  <div className="text-sm font-medium">{item.name}</div>
                  <div className="text-xs text-muted-foreground mt-0.5">{item.description}</div>
                </div>
              ))}
            </div>
          </PageSection>
        )}
      </div>

      <p className="text-xs text-muted-foreground">
        {__('Use these triggers and actions in your Flows.', 'wp-sms')}
      </p>
    </div>
  );
}
