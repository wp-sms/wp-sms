import type { LucideIcon } from 'lucide-react';
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

interface PageSectionProps extends React.ComponentProps<'div'> {
  icon?: LucideIcon;
  title: string;
  description?: React.ReactNode;
  actions?: React.ReactNode;
  active?: boolean;
  contentClassName?: string;
  children: React.ReactNode;
}

export function PageSection({
  icon: Icon,
  title,
  description,
  actions,
  active,
  contentClassName,
  children,
  className,
  ...props
}: PageSectionProps) {
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
