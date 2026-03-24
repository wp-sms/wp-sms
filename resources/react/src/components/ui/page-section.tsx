import { useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import { ChevronDown } from 'lucide-react';
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

interface PageSectionProps extends React.ComponentProps<'div'> {
  icon?: LucideIcon;
  title: string;
  description?: React.ReactNode;
  actions?: React.ReactNode;
  active?: boolean;
  collapsible?: boolean;
  defaultOpen?: boolean;
  storageKey?: string;
  contentClassName?: string;
  children: React.ReactNode;
}

function getInitialOpen(storageKey: string | undefined, defaultOpen: boolean): boolean {
  if (!storageKey) return defaultOpen;
  try {
    const stored = sessionStorage.getItem(`ps-${storageKey}`);
    if (stored !== null) return stored === '1';
  } catch { /* noop */ }
  return defaultOpen;
}

export function PageSection({
  icon: Icon,
  title,
  description,
  actions,
  active,
  collapsible,
  defaultOpen = true,
  storageKey,
  contentClassName,
  children,
  className,
  ...props
}: PageSectionProps) {
  const [open, setOpen] = useState(() => getInitialOpen(storageKey, defaultOpen));

  const handleOpenChange = (v: boolean) => {
    setOpen(v);
    if (storageKey) {
      try { sessionStorage.setItem(`ps-${storageKey}`, v ? '1' : '0'); } catch { /* noop */ }
    }
  };

  if (!collapsible) {
    return (
      <Card active={active} className={className} {...props}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
            {title}
          </CardTitle>
          {description && <CardDescription>{description}</CardDescription>}
          {actions && <CardAction>{actions}</CardAction>}
        </CardHeader>
        <CardContent className={contentClassName}>
          {children}
        </CardContent>
      </Card>
    );
  }

  return (
    <Card active={active} className={className} {...props}>
      <Collapsible open={open} onOpenChange={handleOpenChange}>
        <CollapsibleTrigger asChild>
          <CardHeader className="cursor-pointer select-none">
            <CardTitle className="flex items-center gap-2 text-base">
              {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
              {title}
              <ChevronDown className={cn(
                "ml-auto h-4 w-4 text-muted-foreground transition-transform",
                open && "rotate-180"
              )} />
            </CardTitle>
            {description && <CardDescription>{description}</CardDescription>}
            {actions && <CardAction onClick={(e) => e.stopPropagation()}>{actions}</CardAction>}
          </CardHeader>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <CardContent className={contentClassName}>
            {children}
          </CardContent>
        </CollapsibleContent>
      </Collapsible>
    </Card>
  );
}
