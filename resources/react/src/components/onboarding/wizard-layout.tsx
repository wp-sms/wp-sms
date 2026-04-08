import { type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { Logo } from '@/components/logo';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const STEP_LABELS = [
  __('Goals', 'wp-sms'),
  __('Gateway', 'wp-sms'),
  __('Configure', 'wp-sms'),
  __('Done', 'wp-sms'),
];

const SKIP_BUTTON_BASE =
  'text-muted-foreground hover:text-foreground transition-colors disabled:opacity-50 disabled:pointer-events-none';

interface WizardLayoutProps {
  currentStep: number;
  totalSteps?: number;
  onSkip?: () => void;
  skipDisabled?: boolean;
  onBack?: () => void;
  onContinue?: () => void;
  onSkipStep?: () => void;
  continueDisabled?: boolean;
  continueLabel?: string;
  skipStepLabel?: string;
  showFooter?: boolean;
  children: ReactNode;
}

export function WizardLayout({
  currentStep,
  totalSteps = 4,
  onSkip,
  skipDisabled,
  onBack,
  onContinue,
  onSkipStep,
  continueDisabled,
  continueLabel,
  skipStepLabel,
  showFooter = true,
  children,
}: WizardLayoutProps) {
  const isFirstStep = currentStep === 0;
  const isLastStep = currentStep >= totalSteps - 1;

  return (
    <div className="min-h-[calc(100vh-80px)] flex flex-col bg-background">
      {/* Header */}
      <header className="flex h-[60px] items-center justify-between border-b-2 bg-card px-8">
        <div className="flex items-center gap-2.5">
          <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary">
            <Logo className="h-5 w-5 text-primary-foreground" />
          </div>
          <span className="text-[15px] font-bold tracking-tight">{'WSMS'}</span>
        </div>

        {/* Step Progress */}
        <div className="hidden sm:flex items-center">
          {STEP_LABELS.map((label, i) => (
            <div key={i} className="flex items-center">
              <div className="flex items-center gap-2">
                <div
                  className={cn(
                    'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-colors',
                    i < currentStep && 'bg-green-600 text-white',
                    i === currentStep && 'bg-primary text-primary-foreground',
                    i > currentStep && 'bg-transparent text-muted-foreground border-2 border-border',
                  )}
                  aria-current={i === currentStep ? 'step' : undefined}
                >
                  {i < currentStep ? (
                    <Check className="h-3.5 w-3.5" />
                  ) : (
                    i + 1
                  )}
                </div>
                <span
                  className={cn(
                    'text-xs font-semibold hidden md:inline',
                    i === currentStep ? 'text-foreground' : 'text-muted-foreground',
                  )}
                >
                  {label}
                </span>
              </div>
              {i < totalSteps - 1 && (
                <div
                  className={cn(
                    'h-0.5 w-9 mx-1.5 transition-colors',
                    i < currentStep ? 'bg-green-600' : 'bg-border',
                  )}
                />
              )}
            </div>
          ))}
        </div>

        {/* Skip */}
        {!isLastStep && onSkip ? (
          <button
            onClick={onSkip}
            disabled={skipDisabled}
            className={cn('text-xs font-semibold', SKIP_BUTTON_BASE)}
          >
            {__('Skip setup', 'wp-sms')} <ArrowRight className="ms-1 size-3.5 rtl:scale-x-[-1]" />
          </button>
        ) : (
          <div className="w-20" />
        )}
      </header>

      {/* Content */}
      <main className="flex-1 flex justify-center py-10 px-8">
        <div className="w-full max-w-[640px] animate-fade-up">
          {children}
        </div>
      </main>

      {/* Footer */}
      {showFooter && !isLastStep && (
        <footer className="flex h-[60px] items-center justify-between border-t-2 bg-card px-8">
          {onBack && !isFirstStep ? (
            <Button variant="ghost" onClick={onBack}>
              <ArrowLeft className="me-1 size-4 rtl:scale-x-[-1]" />
              {__('Back', 'wp-sms')}
            </Button>
          ) : onSkip ? (
            <button
              onClick={onSkip}
              disabled={skipDisabled}
              className={cn('text-sm font-medium', SKIP_BUTTON_BASE)}
            >
              {__('Skip setup', 'wp-sms')}
            </button>
          ) : (
            <div />
          )}

          <div className="flex items-center gap-4">
            {onSkipStep && skipStepLabel && (
              <button onClick={onSkipStep} className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                {skipStepLabel}
              </button>
            )}
            {onContinue && (
              <Button onClick={onContinue} disabled={continueDisabled}>
                {continueLabel ?? __('Continue', 'wp-sms')}
                <ArrowRight className="ms-1 size-4 rtl:scale-x-[-1]" />
              </Button>
            )}
          </div>
        </footer>
      )}
    </div>
  );
}
