import { formatDate } from '@/lib/format';
import type { ContactDetail } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { ExternalLink, User } from 'lucide-react';

interface ContactWpUserInfoProps {
  wpUser: NonNullable<ContactDetail['wp_user']>;
}

export function ContactWpUserInfo({ wpUser }: ContactWpUserInfoProps) {
  return (
    <div className="rounded-lg border border-border/50 p-3 space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <User className="h-3.5 w-3.5 text-muted-foreground" />
          WordPress User
        </div>
        <a
          href={wpUser.edit_url}
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs text-primary hover:underline flex items-center gap-1"
        >
          View in WP <ExternalLink className="h-3 w-3" />
        </a>
      </div>
      <div className="space-y-1 text-sm">
        <div className="flex items-center gap-2">
          <span className="text-muted-foreground">Username:</span>
          <span>{wpUser.username}</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-muted-foreground">Roles:</span>
          <div className="flex gap-1">
            {wpUser.roles.map((role) => (
              <Badge key={role} variant="secondary" className="text-[10px] px-1.5 py-0">
                {role}
              </Badge>
            ))}
          </div>
        </div>
        {wpUser.registered && (
          <div className="flex items-center gap-2">
            <span className="text-muted-foreground">Registered:</span>
            <span>{formatDate(wpUser.registered)}</span>
          </div>
        )}
      </div>
    </div>
  );
}
