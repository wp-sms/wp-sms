import type { LucideIcon } from 'lucide-react';
import { Settings } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface ChannelRowProps {
  icon: LucideIcon | React.ComponentType<React.SVGProps<SVGSVGElement>>;
  title: string;
  description?: string;
  enabled?: boolean;
  onToggle?: (enabled: boolean) => void;
  onConfigure?: () => void;
  crossRefLabel?: string;
  onCrossRefAction?: () => void;
  crossRefActionLabel?: string;
  comingSoon?: boolean;
  disabled?: boolean;
}

export function ChannelRow({
  icon: Icon,
  title,
  description,
  enabled,
  onToggle,
  onConfigure,
  crossRefLabel,
  onCrossRefAction,
  crossRefActionLabel,
  comingSoon,
  disabled,
}: ChannelRowProps) {
  const isInteractive = !comingSoon && !disabled;

  return (
    <div className={cn(
      'flex items-center gap-3 py-3',
      !isInteractive && 'opacity-50',
    )}>
      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted">
        <Icon className="h-4 w-4 text-muted-foreground" />
      </div>

      <div className="min-w-0 flex-1">
        <div className="text-sm font-medium">{title}</div>
        {crossRefLabel ? (
          <div className="text-xs text-muted-foreground">
            {crossRefLabel}
            {onCrossRefAction && crossRefActionLabel && (
              <>
                {' · '}
                <button
                  type="button"
                  onClick={onCrossRefAction}
                  className="text-primary hover:underline"
                >
                  {crossRefActionLabel}
                </button>
              </>
            )}
          </div>
        ) : description ? (
          <div className="text-xs text-muted-foreground">{description}</div>
        ) : null}
      </div>

      {comingSoon && (
        <Badge variant="secondary" className="shrink-0 text-[10px] px-1.5 py-0">
          Coming Soon
        </Badge>
      )}

      {isInteractive && onConfigure && (
        <button
          type="button"
          onClick={onConfigure}
          className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
          aria-label={`Configure ${title}`}
        >
          <Settings className="h-4 w-4" />
        </button>
      )}

      {isInteractive && onToggle !== undefined && (
        <Switch
          checked={enabled}
          onCheckedChange={onToggle}
          aria-label={`Toggle ${title}`}
          className="shrink-0"
        />
      )}
    </div>
  );
}
