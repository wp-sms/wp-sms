import { __ } from '@wordpress/i18n';
import type { LucideIcon } from 'lucide-react';
import { Pencil, ChevronUp, Trash2 } from 'lucide-react';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { useConfirm } from '@/components/confirm-provider';

interface CollapsibleCardProps {
  icon: LucideIcon;
  iconBg: string;
  iconFg: string;
  borderColor: string;
  label: string;
  summary: string;
  statusColor: string;
  statusLabel: string;
  isExpanded: boolean;
  onToggle: () => void;
  onDelete?: () => void;
  number?: number;
  children: React.ReactNode;
}

export function CollapsibleCard({
  icon: Icon,
  iconBg,
  iconFg,
  borderColor,
  label,
  summary,
  statusColor,
  statusLabel,
  isExpanded,
  onToggle,
  onDelete,
  number,
  children,
}: CollapsibleCardProps) {
  const confirm = useConfirm();

  const handleDelete = async () => {
    if (!onDelete) return;
    const ok = await confirm({
      title: __('Delete step?', 'wp-sms'),
      description: __('This step will be permanently removed from the flow.', 'wp-sms'),
      confirmLabel: __('Delete', 'wp-sms'),
      variant: 'destructive',
    });
    if (ok) onDelete();
  };

  return (
    <Collapsible open={isExpanded} onOpenChange={onToggle}>
      <div
        className={cn(
          'rounded-lg border bg-card transition-shadow border-s-4',
          borderColor,
          isExpanded && 'shadow-sm',
        )}
      >
        {/* Header */}
        <div
          className={cn(
            'flex items-center gap-2 px-3 py-2.5',
            !isExpanded && 'cursor-pointer hover:bg-muted/30 transition-colors rounded-lg',
          )}
          onClick={!isExpanded ? onToggle : undefined}
        >
          <span className={cn('flex h-6 w-6 shrink-0 items-center justify-center rounded-md', iconBg)}>
            <Icon className={cn('h-3.5 w-3.5', iconFg)} />
          </span>

          {number != null && (
            <span className="text-xs font-medium text-muted-foreground">{number}</span>
          )}

          <span className="flex-1 min-w-0 truncate text-sm">
            {isExpanded ? (
              <span className="font-medium">{label}</span>
            ) : (
              <span className="text-foreground">{summary}</span>
            )}
          </span>

          <span className={cn('h-2 w-2 shrink-0 rounded-full', statusColor)} title={statusLabel} />

          <div className="flex items-center gap-0.5 shrink-0">
            <button
              type="button"
              className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:text-foreground transition-colors"
              onClick={(e) => { e.stopPropagation(); onToggle(); }}
            >
              {isExpanded ? <ChevronUp className="h-3.5 w-3.5" /> : <Pencil className="h-3 w-3" />}
            </button>
            {onDelete && (
              <button
                type="button"
                className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:text-destructive transition-colors"
                onClick={(e) => { e.stopPropagation(); void handleDelete(); }}
              >
                <Trash2 className="h-3 w-3" />
              </button>
            )}
          </div>
        </div>

        <CollapsibleContent>
          <div className="border-t border-border/50 px-3 py-3">
            {children}
          </div>
        </CollapsibleContent>
      </div>
    </Collapsible>
  );
}
