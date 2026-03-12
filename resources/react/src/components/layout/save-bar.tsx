import { Button } from '@/components/ui/button';
import type { SaveStatus } from '@/hooks/use-settings';
import { Loader2 } from 'lucide-react';

interface SaveBarProps {
  isDirty: boolean;
  saveStatus: SaveStatus;
  onSave: () => void;
}

export function SaveBar({ isDirty, saveStatus, onSave }: SaveBarProps) {
  if (!isDirty && saveStatus !== 'saving') return null;

  return (
    <div className="fixed bottom-0 left-0 right-0 z-50 border-t border-primary/20 bg-background px-6 py-3" style={{ boxShadow: 'var(--shadow-save-bar)' }}>
      <div className="mx-auto flex max-w-5xl items-center justify-between">
        <div className="flex items-center gap-2 text-sm">
          {saveStatus === 'saving' ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" />
              <span>Saving...</span>
            </>
          ) : (
            <span className="flex items-center gap-2 text-muted-foreground">
              <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
              You have unsaved changes
            </span>
          )}
        </div>
        <Button
          onClick={onSave}
          disabled={!isDirty || saveStatus === 'saving'}
          size="default"
        >
          {saveStatus === 'saving' ? 'Saving...' : 'Save Changes'}
        </Button>
      </div>
    </div>
  );
}
