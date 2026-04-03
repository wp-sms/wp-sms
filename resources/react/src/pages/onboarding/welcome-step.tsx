import { __ } from '@wordpress/i18n';
import { LogIn, Bell, Megaphone, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { cn } from '@/lib/utils';
import { toggleArrayItem } from '@/lib/constants';
import { getConfig, type OnboardingGoal, type DetectedIntegration } from '@/lib/api';

interface GoalCard {
  id: OnboardingGoal;
  icon: typeof LogIn;
  title: string;
  description: string;
}

const GOALS: GoalCard[] = [
  {
    id: 'auth',
    icon: LogIn,
    title: __('Custom Auth & Identity', 'wp-sms'),
    description: __('Replace WordPress login with branded forms. Add MFA, social login, and phone verification.', 'wp-sms'),
  },
  {
    id: 'notifications',
    icon: Bell,
    title: __('SMS & Notifications', 'wp-sms'),
    description: __('Send SMS messages to users when events happen — orders, signups, and more.', 'wp-sms'),
  },
  {
    id: 'campaigns',
    icon: Megaphone,
    title: __('Marketing & Campaigns', 'wp-sms'),
    description: __('Build contact lists, send bulk campaigns, and automate messaging flows.', 'wp-sms'),
  },
];

interface WelcomeStepProps {
  selectedGoals: OnboardingGoal[];
  onGoalsChange: (goals: OnboardingGoal[]) => void;
  onNavigate: (section: string) => void;
}

export function WelcomeStep({ selectedGoals, onGoalsChange, onNavigate }: WelcomeStepProps) {
  const { detectedIntegrations, detectedMigrations, onboarding } = getConfig();
  const migrationDeferred = onboarding?.migration_deferred ?? false;

  const toggleGoal = (id: OnboardingGoal) => {
    onGoalsChange(toggleArrayItem(selectedGoals, id, !selectedGoals.includes(id)));
  };

  const getIntegrationBadges = (goalId: string): DetectedIntegration[] => {
    return detectedIntegrations.filter((i) => i.goals.includes(goalId));
  };

  const pendingMigrations = detectedMigrations.filter(
    (m) => m.migrationStatus !== 'completed' && !migrationDeferred,
  );

  return (
    <div className="space-y-7">
      {/* Migration detection card */}
      {pendingMigrations.length > 0 && (
        <Alert className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
          <AlertDescription className="space-y-3">
            <div>
              <p className="font-medium text-foreground">
                {pendingMigrations.length === 1
                  ? __('We detected data from another plugin', 'wp-sms')
                  : __('We detected data from other plugins', 'wp-sms')}
              </p>
              {pendingMigrations.map((m) => (
                <p key={m.id} className="text-sm text-muted-foreground mt-1">
                  {m.name}{m.summary.length > 0 ? ` — ${m.summary.join(', ')}` : ''}
                </p>
              ))}
            </div>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" onClick={() => onNavigate('settings/migration')}>
                {__('Import first', 'wp-sms')}
              </Button>
              <Button size="sm" variant="ghost" onClick={() => onGoalsChange(selectedGoals)}>
                {__('Set up WSMS first', 'wp-sms')}
              </Button>
            </div>
          </AlertDescription>
        </Alert>
      )}

      {/* Heading */}
      <div>
        <h1 className="text-xl font-extrabold tracking-tight">
          {__('Welcome to WSMS', 'wp-sms')}
        </h1>
        <p className="text-sm text-muted-foreground mt-1.5 leading-relaxed">
          {__('Tell us what you\'d like to do, and we\'ll customize your experience. You can always change this later.', 'wp-sms')}
        </p>
      </div>

      {/* Goal cards — vertical list matching mockup */}
      <div className="flex flex-col gap-3">
        {GOALS.map((goal) => {
          const Icon = goal.icon;
          const selected = selectedGoals.includes(goal.id);
          const badges = getIntegrationBadges(goal.id);

          return (
            <button
              key={goal.id}
              type="button"
              role="checkbox"
              aria-checked={selected}
              onClick={() => toggleGoal(goal.id)}
              className={cn(
                'flex gap-4 p-5 bg-card border-2 rounded-md text-start transition-all cursor-pointer select-none',
                'hover:border-border/80',
                selected && 'border-primary bg-primary/[0.02] border-s-[3px]',
                !selected && 'border-border',
              )}
            >
              {/* Icon */}
              <div
                className={cn(
                  'flex h-10 w-10 shrink-0 items-center justify-center rounded transition-colors',
                  selected ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                )}
              >
                <Icon className="h-5 w-5" />
              </div>

              {/* Body */}
              <div className="flex-1 min-w-0">
                <div className="text-sm font-semibold">{goal.title}</div>
                <div className="text-sm text-muted-foreground mt-0.5 leading-relaxed">{goal.description}</div>
                {badges.length > 0 && (
                  <div className="flex flex-wrap gap-1.5 mt-2">
                    {badges.map((b) => (
                      <Badge key={b.id} variant="secondary" className="text-xs font-semibold">
                        {b.name}
                      </Badge>
                    ))}
                  </div>
                )}
              </div>

              {/* Checkmark */}
              <div
                className={cn(
                  'flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 mt-0.5 transition-all',
                  selected ? 'bg-primary border-primary' : 'border-border',
                )}
              >
                {selected && <Check className="h-3 w-3 text-primary-foreground" />}
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}
