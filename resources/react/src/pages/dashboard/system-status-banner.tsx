import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { AlertTriangle, AlertCircle, X } from 'lucide-react';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { DashboardSummaryResponse } from '@/lib/api';

interface SystemStatusBannerProps {
  health: DashboardSummaryResponse['system_health'];
  onNavigate: (section: string) => void;
}

export function SystemStatusBanner({ health, onNavigate }: SystemStatusBannerProps) {
  const [dismissed, setDismissed] = useState(false);

  if (health.level === 'healthy') return null;
  if (health.level === 'warnings' && dismissed) return null;

  const isCritical = health.level === 'critical';
  const issue = health.top_issue;

  return (
    <Alert variant={isCritical ? 'destructive' : 'warning'} className="animate-fade-up">
      {isCritical
        ? <AlertCircle className="h-4 w-4" />
        : <AlertTriangle className="h-4 w-4" />
      }
      <AlertTitle className="flex items-center gap-2">
        <span className="flex-1">{issue?.label ?? health.summary}</span>
        {!isCritical && (
          <Button variant="ghost" size="icon-sm" className="shrink-0 -my-1" onClick={() => setDismissed(true)}>
            <X className="size-3.5" />
            <span className="sr-only">{__('Dismiss', 'wp-sms')}</span>
          </Button>
        )}
      </AlertTitle>
      <AlertDescription>
        <div className="flex items-center justify-between gap-2 flex-wrap">
          <span>
            {issue?.description ?? health.summary}
          </span>
          <Button
            variant="outline"
            size="sm"
            className="shrink-0 text-xs"
            onClick={() => onNavigate('monitoring/health')}
          >
            {isCritical && issue
              ? sprintf(__('Fix: %s', 'wp-sms'), issue.section)
              : __('View Monitoring', 'wp-sms')
            }
          </Button>
        </div>
      </AlertDescription>
    </Alert>
  );
}
