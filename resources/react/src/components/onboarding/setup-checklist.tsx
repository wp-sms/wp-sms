import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { CheckCircle2, Circle, ChevronDown, PartyPopper, ArrowRight, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { useOnboarding } from '@/hooks/use-onboarding';

interface SetupChecklistProps {
  onNavigate: (section: string) => void;
}

export function SetupChecklist({ onNavigate }: SetupChecklistProps) {
  const { state, checklist, loading, updateState } = useOnboarding();
  const [collapsed, setCollapsed] = useState(false);

  // Don't render if no state, loading, or dismissed
  if (loading || !state) return null;
  if (state.checklist_dismissed) return null;
  // Only show if onboarding is completed or skipped (wizard done)
  if (state.status !== 'completed' && state.status !== 'skipped') return null;

  const completed = checklist.filter((i) => i.completed).length;
  const total = checklist.length;
  const allDone = completed === total && total > 0;
  const percent = total > 0 ? Math.round((completed / total) * 100) : 0;

  // All items done — show slim dismissable bar
  if (allDone) {
    return (
      <Card className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30">
        <CardContent className="flex items-center justify-between py-3">
          <div className="flex items-center gap-2">
            <PartyPopper className="size-4 text-green-600" />
            <span className="text-sm font-medium text-green-700 dark:text-green-400">
              {__('Setup complete! You\'re all set.', 'wp-sms')}
            </span>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => void updateState({ checklist_dismissed: true })}
          >
            {__('Dismiss', 'wp-sms')}
          </Button>
        </CardContent>
      </Card>
    );
  }

  const dismiss = () => void updateState({ checklist_dismissed: true });

  return (
    <Card>
      <Collapsible open={!collapsed} onOpenChange={(open) => setCollapsed(!open)}>
        <CardHeader className="pb-3">
          <div className="flex items-start justify-between">
            <CollapsibleTrigger asChild>
              <button className="flex flex-1 items-center justify-between text-start">
                <div className="space-y-1">
                  <CardTitle className="text-base">
                    {__('Getting started', 'wp-sms')}
                  </CardTitle>
                  <p className="text-sm text-muted-foreground">
                    {completed} {__('of', 'wp-sms')} {total} {__('complete', 'wp-sms')}
                  </p>
                </div>
                <ChevronDown className={cn('size-4 text-muted-foreground transition-transform', !collapsed && 'rotate-180')} />
              </button>
            </CollapsibleTrigger>
            <Button variant="ghost" size="icon-xs" onClick={dismiss} className="text-muted-foreground shrink-0 -mt-1 -me-1">
              <X className="size-3.5" />
              <span className="sr-only">{__('Dismiss', 'wp-sms')}</span>
            </Button>
          </div>
          <Progress value={percent} className="mt-2 h-1.5" />
        </CardHeader>

        <CollapsibleContent>
          <CardContent className="pt-0 space-y-1">
            {checklist.map((item) => (
              <button
                key={item.id}
                onClick={() => onNavigate(item.route)}
                className={cn(
                  'flex w-full items-center gap-3 rounded-md px-3 py-2 text-start transition-colors',
                  item.completed
                    ? 'text-muted-foreground'
                    : 'hover:bg-accent',
                )}
              >
                {item.completed ? (
                  <CheckCircle2 className="size-4 text-green-600 shrink-0" />
                ) : (
                  <Circle className="size-4 text-muted-foreground/40 shrink-0" />
                )}
                <span className={cn('text-sm flex-1', item.completed && 'line-through')}>
                  {item.label}
                </span>
                {!item.completed && (
                  <ArrowRight className="size-3.5 text-muted-foreground" />
                )}
              </button>
            ))}
          </CardContent>
        </CollapsibleContent>
      </Collapsible>
    </Card>
  );
}
