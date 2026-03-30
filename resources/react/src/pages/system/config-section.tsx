import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Copy, Check, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn, copyToClipboard } from '@/lib/utils';
import type { HealthCheck } from '@/lib/api';
import { HealthCheckRow } from './health-check-row';

interface ConfigSectionProps {
  checks: HealthCheck[];
}

export function ConfigSection({ checks }: ConfigSectionProps) {
  const envCheck = checks.find(c => c.id === 'cfg_environment');
  const otherChecks = checks.filter(c => c.id !== 'cfg_environment');

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        {otherChecks.map((check, i) => (
          <HealthCheckRow key={check.id} check={check} index={i} />
        ))}
      </div>

      {envCheck?.details && (
        <EnvironmentInfo details={envCheck.details as Record<string, string | boolean>} />
      )}
    </div>
  );
}

function EnvironmentInfo({ details }: { details: Record<string, string | boolean> }) {
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);

  const handleCopy = async (e: React.MouseEvent) => {
    e.stopPropagation();
    const text = Object.entries(details)
      .map(([k, v]) => `${formatKey(k)}: ${String(v)}`)
      .join('\n');
    await copyToClipboard(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <Collapsible open={open} onOpenChange={setOpen}>
      <CollapsibleTrigger asChild>
        <div className="flex items-center gap-2 cursor-pointer select-none rounded-md border px-3 py-2.5 hover:bg-muted/50 transition-colors">
          <span className="h-2 w-2 shrink-0 rounded-full bg-blue-500" />
          <span className="flex-1 text-sm font-medium">{__('Environment', 'wp-sms')}</span>
          <Button variant="ghost" size="icon-xs" onClick={handleCopy} className="shrink-0">
            {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
          </Button>
          <ChevronDown className={cn(
            'h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform',
            open && 'rotate-180',
          )} />
        </div>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <div className="ms-5 border-s-2 border-border ps-4 py-2">
          <div className="grid gap-1">
            {Object.entries(details).map(([key, value]) => (
              <div key={key} className="flex items-center gap-2 text-xs">
                <span className="text-muted-foreground min-w-[140px]">{formatKey(key)}</span>
                <span className="tabular-nums font-medium">{String(value)}</span>
              </div>
            ))}
          </div>
        </div>
      </CollapsibleContent>
    </Collapsible>
  );
}

function formatKey(key: string): string {
  return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
