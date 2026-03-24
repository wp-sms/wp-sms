import { useContactActivity } from '@/hooks/use-contact-activity';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/ui/empty-state';
import { MessageSquare, UserPlus, RefreshCw, Mail } from 'lucide-react';

interface ContactActivityProps {
  contactId: string | null;
}

function getActivityIcon(type: string) {
  if (type.startsWith('message_')) return MessageSquare;
  if (type === 'contact_created') return UserPlus;
  if (type === 'contact_updated') return RefreshCw;
  return Mail;
}

export function ContactActivity({ contactId }: ContactActivityProps) {
  const { activities, loading, loadMore, hasMore } = useContactActivity(contactId);

  if (loading && !activities.length) {
    return (
      <div className="space-y-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-12 w-full" />
        ))}
      </div>
    );
  }

  if (!activities.length) {
    return <EmptyState compact title="No activity yet" />;
  }

  return (
    <div className="space-y-1">
      {activities.map((activity) => {
        const Icon = getActivityIcon(activity.type);
        return (
          <div key={activity.id} className="flex items-start gap-3 py-2 border-b border-border/30 last:border-0">
            <div className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted">
              <Icon className="h-3 w-3 text-muted-foreground" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm">{activity.description}</p>
              {activity.meta?.body_preview && (
                <p className="text-xs text-muted-foreground mt-0.5 truncate">{String(activity.meta.body_preview)}</p>
              )}
              <p className="text-[10px] text-muted-foreground mt-0.5">
                {new Date(activity.created_at).toLocaleString()}
              </p>
            </div>
          </div>
        );
      })}
      {hasMore && (
        <Button variant="ghost" size="sm" className="w-full mt-2" onClick={loadMore} disabled={loading}>
          Load more
        </Button>
      )}
    </div>
  );
}
