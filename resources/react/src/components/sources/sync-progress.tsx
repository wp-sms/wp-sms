import { useState, useEffect, useRef } from 'react';
import { Progress } from '@/components/ui/progress';
import type { ContactSourceStatus } from '@/lib/api';

interface SyncProgressProps {
  sourceType: string;
  totalAvailable: number;
  getStatus: (type: string) => Promise<ContactSourceStatus>;
  onComplete: () => void;
}

export function SyncProgress({ sourceType, totalAvailable, getStatus, onComplete }: SyncProgressProps) {
  const [synced, setSynced] = useState(0);
  const [status, setStatus] = useState<string>('syncing');
  const intervalRef = useRef<ReturnType<typeof setInterval>>();

  useEffect(() => {
    let cancelled = false;

    const poll = async () => {
      try {
        const res = await getStatus(sourceType);
        if (cancelled) return;

        setStatus((prev) => res.status === prev ? prev : res.status);
        setSynced((prev) => {
          const next = res.stats?.sync_progress?.synced ?? 0;
          return next === prev ? prev : next;
        });

        if (res.status !== 'syncing') {
          clearInterval(intervalRef.current);
          onComplete();
        }
      } catch {
        if (!cancelled) clearInterval(intervalRef.current);
      }
    };

    intervalRef.current = setInterval(poll, 3000);
    poll();

    return () => {
      cancelled = true;
      clearInterval(intervalRef.current);
    };
  }, [sourceType, getStatus, onComplete]);

  const percent = totalAvailable > 0 ? Math.round((synced / totalAvailable) * 100) : 0;

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">
          {status === 'syncing' ? 'Syncing contacts...' : 'Sync complete'}
        </span>
        <span className="font-medium">
          {synced.toLocaleString()}{totalAvailable > 0 ? ` / ${totalAvailable.toLocaleString()}` : ''}
        </span>
      </div>
      <Progress value={percent} />
    </div>
  );
}
