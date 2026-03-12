import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface MethodCardProps {
  title: string;
  description: string;
  enabled: boolean;
  onToggle: (enabled: boolean) => void;
  icon?: LucideIcon;
  children?: ReactNode;
}

export function MethodCard({ title, description, enabled, onToggle, icon: Icon, children }: MethodCardProps) {
  const [open, setOpen] = useState(false);
  const hasConfig = !!children;

  return (
    <Card className={cn(
      'transition-shadow duration-150',
      enabled && 'hover:shadow-[var(--shadow-card-hover)] border-l-2 border-l-primary',
      !enabled && 'opacity-50',
    )}>
      <Collapsible open={open && enabled} onOpenChange={setOpen}>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <div className="space-y-1">
            <CardTitle className="flex items-center gap-2 text-base">
              {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
              {title}
            </CardTitle>
            <CardDescription>{description}</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            {hasConfig && enabled && (
              <CollapsibleTrigger asChild>
                <button
                  className="rounded-md p-1.5 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                  aria-label="Toggle configuration"
                >
                  <ChevronDown
                    className={cn('h-4 w-4 transition-transform', open && 'rotate-180')}
                  />
                </button>
              </CollapsibleTrigger>
            )}
            <Switch
              checked={enabled}
              onCheckedChange={(next) => {
                if (!next) setOpen(false);
                onToggle(next);
              }}
              aria-label={`Toggle ${title}`}
            />
          </div>
        </CardHeader>
        {hasConfig && (
          <CollapsibleContent>
            <CardContent className="border-t pt-4">
              {children}
            </CardContent>
          </CollapsibleContent>
        )}
      </Collapsible>
    </Card>
  );
}
