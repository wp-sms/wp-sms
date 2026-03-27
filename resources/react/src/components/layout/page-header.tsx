import type { LucideIcon } from 'lucide-react';

interface PageHeaderProps {
  icon?: LucideIcon;
  title: string;
  metadata?: React.ReactNode;
  actions?: React.ReactNode;
  children?: React.ReactNode;
}

export function PageHeader({ icon: Icon, title, metadata, actions, children }: PageHeaderProps) {
  return (
    <div className="mb-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-xl font-extrabold tracking-[-0.02em]">
            {Icon && <Icon className="h-5 w-5 text-muted-foreground" />}
            {title}
            {metadata && (
              <span className="hidden text-sm font-normal text-muted-foreground sm:inline">
                · {metadata}
              </span>
            )}
          </h1>
          {metadata && (
            <p className="mt-0.5 text-sm text-muted-foreground sm:hidden">{metadata}</p>
          )}
        </div>
        {actions && (
          <div className="flex items-center gap-2">{actions}</div>
        )}
      </div>
      {children}
    </div>
  );
}
