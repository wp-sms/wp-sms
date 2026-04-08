import { __ } from '@wordpress/i18n';
import { Sparkles, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useOnboarding } from '@/hooks/use-onboarding';

/**
 * Inline notice prompting the user to enter or resume the setup wizard.
 *
 * Visible while onboarding is `pending` or `in_progress`. The wording adapts:
 * - `in_progress` → resume copy (the common case — user walked away mid-flow)
 * - `pending` → start copy (rare — pending normally auto-redirects via
 *   App.tsx, but the migration page can be reached directly)
 *
 * Uses a title + subtitle layout in a bordered container (no Card wrapper —
 * Card's structural padding bloated the height). The circular icon, two-line
 * text, and outlined CTA give it enough presence to read as actionable
 * without dominating the dashboard.
 *
 * Reads live state from `useOnboarding()` (not the page-load `getConfig()`
 * snapshot) so it disappears immediately after the wizard moves to
 * `completed` / `skipped`, without requiring a full page reload.
 */
export function ContinueSetupCard() {
  const { state, loading } = useOnboarding();

  if (loading || !state) return null;
  if (state.status !== 'pending' && state.status !== 'in_progress') return null;

  const isResume = state.status === 'in_progress';

  return (
    <div className="flex items-center justify-between gap-4 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3">
      <div className="flex items-center gap-3 min-w-0">
        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
          <Sparkles className="size-4 text-primary" />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-semibold truncate">
            {isResume
              ? __('Setup in progress', 'wp-sms')
              : __('Welcome to WSMS', 'wp-sms')}
          </p>
          <p className="text-xs text-muted-foreground truncate">
            {isResume
              ? __('Pick up where you left off and finish configuring WSMS', 'wp-sms')
              : __('Run the setup wizard to configure your gateways and channels', 'wp-sms')}
          </p>
        </div>
      </div>
      <Button
        variant="outline"
        size="sm"
        className="shrink-0 h-8 border-primary/30 text-primary hover:text-primary hover:bg-primary/10"
        onClick={() => {
          window.location.hash = 'onboarding';
        }}
      >
        {isResume ? __('Resume setup', 'wp-sms') : __('Get started', 'wp-sms')}
        <ArrowRight className="size-3.5 ms-1 rtl:scale-x-[-1]" />
      </Button>
    </div>
  );
}
