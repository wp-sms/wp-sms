import { useState, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { WizardLayout } from '@/components/onboarding/wizard-layout';
import { WelcomeStep } from './welcome-step';
import { GatewayStep } from './gateway-step';
import { ConfigStep } from './config-step';
import { CompleteStep } from './complete-step';
import { useOnboarding } from '@/hooks/use-onboarding';
import { useGateways } from '@/hooks/use-gateways';
import { api, type OnboardingGoal } from '@/lib/api';

interface OnboardingWizardProps {
  onComplete: () => void;
  onNavigate: (section: string) => void;
}

export function OnboardingWizard({ onComplete, onNavigate }: OnboardingWizardProps) {
  const { state, updateState } = useOnboarding();
  const gatewaysHook = useGateways();

  // Always start at 0 when status is pending (covers re-run case).
  // Only restore from server if status is in_progress (mid-wizard resume).
  const initialStep = state?.status === 'in_progress' ? (state.current_step ?? 0) : 0;

  const [step, setStep] = useState(initialStep);
  const [goals, setGoals] = useState<OnboardingGoal[]>(state?.goals ?? []);
  const [authEnabled, setAuthEnabled] = useState(false);
  const [sitePhone, setSitePhone] = useState('');

  const goToStep = useCallback((nextStep: number) => {
    setStep(nextStep);
    void updateState({ current_step: nextStep, status: 'in_progress' });
  }, [updateState]);

  const handleSkip = useCallback(() => {
    void updateState({ status: 'skipped' });
    onComplete();
  }, [updateState, onComplete]);

  const handleGoalsContinue = useCallback(() => {
    void updateState({ goals });
    goToStep(1);
  }, [goals, updateState, goToStep]);

  const handleGatewayContinue = useCallback(() => {
    goToStep(2);
  }, [goToStep]);

  const handleConfigContinue = useCallback(async () => {
    try {
      const settings: Record<string, unknown> = {};
      if (goals.includes('auth')) settings.auth_enabled = authEnabled;
      if (sitePhone.trim()) settings.site_phone = sitePhone.trim();
      if (Object.keys(settings).length > 0) {
        await api.put('auth/admin/settings', settings);
      }
    } catch {
      // Non-blocking — user can fix settings later.
    }
    goToStep(3);
  }, [goals, authEnabled, sitePhone, goToStep]);

  const handleFinish = useCallback(() => {
    void updateState({ status: 'completed', current_step: 3 });
    onComplete();
  }, [updateState, onComplete]);

  const getFooterProps = () => {
    switch (step) {
      case 0:
        return {
          onContinue: handleGoalsContinue,
          continueDisabled: goals.length === 0,
        };
      case 1:
        return {
          onBack: () => goToStep(0),
          onContinue: handleGatewayContinue,
          onSkipStep: handleGatewayContinue,
          skipStepLabel: __('I\'ll do this later', 'wp-sms'),
        };
      case 2:
        return {
          onBack: () => goToStep(1),
          onContinue: handleConfigContinue,
        };
      default:
        return { showFooter: false as const };
    }
  };

  const footerProps = getFooterProps();

  return (
    <WizardLayout
      currentStep={step}
      onSkip={step < 3 ? handleSkip : undefined}
      {...footerProps}
    >
      {step === 0 && (
        <WelcomeStep
          selectedGoals={goals}
          onGoalsChange={setGoals}
          onNavigate={onNavigate}
        />
      )}
      {step === 1 && (
        <GatewayStep goals={goals} gatewaysHook={gatewaysHook} />
      )}
      {step === 2 && (
        <ConfigStep
          goals={goals}
          authEnabled={authEnabled}
          onAuthEnabledChange={setAuthEnabled}
          sitePhone={sitePhone}
          onSitePhoneChange={setSitePhone}
        />
      )}
      {step === 3 && (
        <CompleteStep
          goals={goals}
          authEnabled={authEnabled}
          sitePhone={sitePhone}
          onFinish={handleFinish}
          gatewaysHook={gatewaysHook}
        />
      )}
    </WizardLayout>
  );
}
