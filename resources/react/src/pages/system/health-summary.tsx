import { __ } from '@wordpress/i18n';
import { CheckCircle, AlertTriangle, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SectionStatus, SystemHealthResponse } from '@/lib/api';

const SECTION_LABELS: Record<string, string> = {
  messaging: __('Messaging', 'wp-sms'),
  auth: __('Auth', 'wp-sms'),
  background: __('Background', 'wp-sms'),
  configuration: __('Config', 'wp-sms'),
  data_security: __('Data', 'wp-sms'),
};

const SECTION_IDS: Record<string, string> = {
  messaging: 'health-messaging',
  auth: 'health-auth',
  background: 'health-background',
  configuration: 'health-config',
  data_security: 'health-data-security',
};

const STATUS_BADGE_VARIANT: Record<SectionStatus, 'success' | 'warning' | 'destructive'> = {
  healthy: 'success',
  warning: 'warning',
  critical: 'destructive',
};

interface HealthSummaryProps {
  overallStatus: SystemHealthResponse['overall_status'];
}

export function HealthSummary({ overallStatus }: HealthSummaryProps) {
  const { level, summary, section_statuses } = overallStatus;

  const scrollToSection = (sectionKey: string) => {
    const id = SECTION_IDS[sectionKey];
    if (id) {
      document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  if (level === 'healthy') {
    return (
      <Alert variant="success" className="animate-fade-up">
        <CheckCircle className="h-4 w-4" />
        <AlertTitle>{__('All systems operational', 'wp-sms')}</AlertTitle>
        <AlertDescription>{summary}</AlertDescription>
      </Alert>
    );
  }

  const isCritical = level === 'critical';
  const nonHealthySections = Object.entries(section_statuses).filter(([, s]) => s !== 'healthy');

  return (
    <Alert variant={isCritical ? 'destructive' : 'warning'} className="animate-fade-up">
      {isCritical ? <AlertCircle className="h-4 w-4" /> : <AlertTriangle className="h-4 w-4" />}
      <AlertTitle>{summary}</AlertTitle>
      <AlertDescription>
        <div className="mt-2 flex flex-wrap gap-1.5">
          {nonHealthySections.map(([key, status]) => (
            <button
              key={key}
              type="button"
              onClick={() => scrollToSection(key)}
              className="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-sm"
            >
              <Badge
                variant={STATUS_BADGE_VARIANT[status]}
                className={cn(
                  'cursor-pointer transition-opacity hover:opacity-80',
                  status === 'critical' && 'animate-pulse',
                )}
              >
                {SECTION_LABELS[key] ?? key}
              </Badge>
            </button>
          ))}
        </div>
      </AlertDescription>
    </Alert>
  );
}
