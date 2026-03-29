import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import { useSaveBarState } from '@/contexts/save-bar-context';
import { Loader2 } from 'lucide-react';

export function SaveBar() {
  const { isDirty, saveStatus, onSave } = useSaveBarState();

  if (!isDirty && saveStatus !== 'saving') return null;

  return (
    <div className="sticky bottom-0 z-40 border-t-2 border-t-primary bg-background px-6 py-3 animate-fade-up" style={{ boxShadow: 'var(--shadow-save-bar)' }}>
      <div className="flex items-center justify-between">
        <div role="status" aria-live="polite" className="flex items-center gap-2 text-sm">
          {saveStatus === 'saving' ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" />
              <span>{__('Saving...', 'wp-sms')}</span>
            </>
          ) : (
            <span className="flex items-center gap-2 text-muted-foreground">
              <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" aria-hidden="true" />
              {__('You have unsaved changes', 'wp-sms')}
            </span>
          )}
        </div>
        <Button
          onClick={onSave}
          disabled={!isDirty || saveStatus === 'saving'}
          aria-busy={saveStatus === 'saving'}
          size="default"
        >
          {saveStatus === 'saving' ? __('Saving...', 'wp-sms') : __('Save Changes', 'wp-sms')}
        </Button>
      </div>
    </div>
  );
}
