import { Card, CardHeader } from '@/components/ui/card';
import { Sparkles } from 'lucide-react';

interface ComingSoonProps {
  title: string;
  description?: string;
}

export function ComingSoon({ title, description }: ComingSoonProps) {
  return (
    <Card className="border-2 border-dashed bg-muted/30 shadow-none">
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted">
            <Sparkles className="h-4 w-4 text-muted-foreground" />
          </div>
          <div>
            <p className="text-sm font-medium text-foreground/70">{title}</p>
            {description && (
              <p className="text-sm text-muted-foreground">{description}</p>
            )}
            <p className="mt-1 text-xs text-muted-foreground/70">Coming in a future update</p>
          </div>
        </div>
      </CardHeader>
    </Card>
  );
}
