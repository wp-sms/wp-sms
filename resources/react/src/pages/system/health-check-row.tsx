import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ChevronDown, ExternalLink, Loader2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { HealthCheck } from '@/lib/api';

const STATUS_DOT: Record<string, string> = {
  pass: 'bg-emerald-500',
  warn: 'bg-amber-500',
  fail: 'bg-destructive',
  skip: 'bg-muted-foreground/40',
  info: 'bg-blue-500',
};

const STATUS_BADGE: Record<string, { variant: 'success' | 'warning' | 'destructive' | 'neutral' | 'info'; label: string }> = {
  pass: { variant: 'success', label: 'Pass' },
  warn: { variant: 'warning', label: 'Warning' },
  fail: { variant: 'destructive', label: 'Fail' },
  skip: { variant: 'neutral', label: 'Skipped' },
  info: { variant: 'info', label: 'Info' },
};

interface HealthCheckRowProps {
  check: HealthCheck;
  index: number;
  onLazyLoad?: () => void;
  lazyLoading?: boolean;
}

export function HealthCheckRow({ check, index, onLazyLoad, lazyLoading }: HealthCheckRowProps) {
  const [open, setOpen] = useState(false);
  const hasDetails = check.resolution || check.details || check.link;
  const badge = STATUS_BADGE[check.status] ?? STATUS_BADGE.skip;

  if (check.lazy && check.status === 'skip') {
    return (
      <div
        className="flex items-center gap-3 rounded-md border px-3 py-2.5 animate-fade-up"
        style={{ animationDelay: `${index * 40}ms` }}
      >
        <span className={cn('h-2 w-2 shrink-0 rounded-full', STATUS_DOT[check.status])} />
        <span className="flex-1 text-sm">{check.label}</span>
        <Button variant="outline" size="xs" onClick={onLazyLoad} disabled={lazyLoading}>
          {lazyLoading && <Loader2 className="me-1.5 h-3 w-3 animate-spin" />}
          {__('Check now', 'wp-sms')}
        </Button>
      </div>
    );
  }

  const row = (
    <div
      className={cn(
        'flex items-center gap-3 rounded-md border px-3 py-2.5 animate-fade-up',
        hasDetails && 'cursor-pointer',
      )}
      style={{ animationDelay: `${index * 40}ms` }}
    >
      <span className={cn('h-2 w-2 shrink-0 rounded-full', STATUS_DOT[check.status])} />
      <div className="flex-1 min-w-0">
        <span className="text-sm font-medium">{check.label}</span>
        <p className="text-xs text-muted-foreground break-words">{check.message}</p>
      </div>
      <Badge variant={badge.variant} className="shrink-0">{__(badge.label, 'wp-sms')}</Badge>
      {hasDetails && (
        <ChevronDown className={cn(
          'h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform',
          open && 'rotate-180',
        )} />
      )}
    </div>
  );

  if (!hasDetails) return row;

  return (
    <Collapsible open={open} onOpenChange={setOpen}>
      <CollapsibleTrigger asChild>{row}</CollapsibleTrigger>
      <CollapsibleContent>
        <div className="ms-5 border-s-2 border-border ps-4 pb-1 pt-2 space-y-2">
          {check.resolution && (
            <p className="text-xs text-muted-foreground">{check.resolution}</p>
          )}
          {check.link && (
            <Button variant="link" size="xs" asChild className="h-auto p-0">
              <a href={check.link}>
                {__('Go to settings', 'wp-sms')} <ExternalLink className="ms-1 h-3 w-3" />
              </a>
            </Button>
          )}
          {check.details && (
            <HealthCheckDetails details={check.details} />
          )}
        </div>
      </CollapsibleContent>
    </Collapsible>
  );
}

function HealthCheckDetails({ details }: { details: Record<string, unknown> }) {
  // Render details as key-value pairs for simple objects
  const entries = Object.entries(details);
  if (entries.length === 0) return null;

  return (
    <div className="grid gap-1">
      {entries.map(([key, value]) => {
        if (value === null || value === undefined) return null;

        // Nested objects (like channel stats) — render as sub-list
        if (typeof value === 'object' && !Array.isArray(value)) {
          return (
            <div key={key} className="space-y-0.5">
              <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{formatKey(key)}</span>
              <div className="grid gap-0.5 ps-2">
                {Object.entries(value as Record<string, unknown>).map(([subKey, subVal]) => (
                  <div key={subKey} className="flex items-center gap-2 text-xs">
                    <span className="text-muted-foreground">{formatKey(subKey)}:</span>
                    <span className="tabular-nums">{typeof subVal === 'object' ? JSON.stringify(subVal) : String(subVal)}</span>
                  </div>
                ))}
              </div>
            </div>
          );
        }

        return (
          <div key={key} className="flex items-center gap-2 text-xs">
            <span className="text-muted-foreground">{formatKey(key)}:</span>
            <span className="tabular-nums">{String(value)}</span>
          </div>
        );
      })}
    </div>
  );
}

function formatKey(key: string): string {
  return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
