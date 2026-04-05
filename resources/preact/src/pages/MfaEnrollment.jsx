import { useEffect, useState } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { useEnrollmentWizard } from '../hooks/useEnrollmentWizard';
import { loadMfaMethods, checkPasskeySupport } from '../utils/mfa-enrollment';
import { redirectTo } from '../utils/auth';
import { getBaseUrl, getQueryParam } from '../utils/urls';
import { api } from '../api/client';
import { loadCurrentUser, enrolledFactors } from '../signals/user';
import { currentUser } from '../signals/auth';
import { Spinner } from '../components/ui/Spinner';
import { Alert } from '../components/ui/Alert';
import { AuthLayout } from '../layouts/AuthLayout';

import {
    wizardStep, wizardMode, selectedChannel, availableMethods,
    gracePeriodInfo, wizardError, resetWizard,
    loadResumeState,
} from '../signals/enrollment';

import { WizardLayout } from '../components/enrollment/WizardLayout';
import { WelcomeStep } from '../components/enrollment/WelcomeStep';
import { SelectMethodStep } from '../components/enrollment/SelectMethodStep';
import { EnrollStep } from '../components/enrollment/EnrollStep';
import { BackupCodesStep } from '../components/enrollment/BackupCodesStep';
import { SuccessStep } from '../components/enrollment/SuccessStep';
import { SessionResumeStep } from '../components/enrollment/SessionResumeStep';
import { AccessDeniedStep } from '../components/enrollment/AccessDeniedStep';
import { UnsupportedStep } from '../components/enrollment/UnsupportedStep';
import { NoMethodsStep } from '../components/enrollment/NoMethodsStep';

export function MfaEnrollment() {
    const authed = useAuthGuard();
    const [initializing, setInitializing] = useState(true);
    const [initError, setInitError] = useState(null);
    const [resumeState, setResumeState] = useState(null);

    const wizard = useEnrollmentWizard();

    useEffect(() => {
        if (!authed) return;
        initWizard();
    }, [authed]); // eslint-disable-line react-hooks/exhaustive-deps

    async function initWizard() {
        setInitializing(true);
        setInitError(null);
        resetWizard();

        try {
            const [methods, configRes] = await Promise.all([
                loadMfaMethods(),
                api.get('/auth/config').catch(() => null),
                currentUser.value ? Promise.resolve() : loadCurrentUser(),
            ]);

            const mode = getQueryParam('mode') || 'forced';
            wizardMode.value = mode;

            if (mode === 'forced' && configRes && !configRes.enrollment_required) {
                redirectTo(getBaseUrl());
                return;
            }

            if (configRes?.grace_period) {
                const gp = configRes.grace_period;
                const expiresAt = gp.expires_at ? new Date(gp.expires_at) : null;
                const remainingMs = expiresAt ? expiresAt.getTime() - Date.now() : 0;
                gracePeriodInfo.value = {
                    remaining_days: gp.remaining_days ?? Math.ceil(remainingMs / 86400000),
                    remaining_hours: Math.max(0, Math.floor(remainingMs / 3600000)),
                    expires_at: gp.expires_at,
                };

                if (mode !== 'grace' && mode !== 'forced') {
                    wizardMode.value = 'grace';
                }
            }

            const selectableMethods = methods.filter((m) => m.id !== 'backup_codes');
            availableMethods.value = selectableMethods;

            if (selectableMethods.length === 0) {
                wizardStep.value = 'no_methods';
                setInitializing(false);
                return;
            }

            if (selectableMethods.length === 1 && selectableMethods[0].id === 'passkey') {
                const support = checkPasskeySupport();
                if (!support.supported) {
                    wizardStep.value = 'unsupported';
                    setInitializing(false);
                    return;
                }
            }

            const factors = enrolledFactors.value;
            const activeNonBackup = factors.filter((f) => f.channel_id !== 'backup_codes');
            const hasBackup = factors.some((f) => f.channel_id === 'backup_codes');

            if (activeNonBackup.length > 0 && hasBackup) {
                redirectTo(getBaseUrl());
                return;
            }

            if (activeNonBackup.length > 0 && !hasBackup) {
                wizardStep.value = 'success';
                setInitializing(false);
                return;
            }

            const resume = loadResumeState(selectableMethods.map((m) => m.id));
            if (resume && resume.channel) {
                setResumeState(resume);
                wizardStep.value = 'resume';
                setInitializing(false);
                return;
            }

            wizardStep.value = 'welcome';
        } catch (err) {
            setInitError(err.message || __('Failed to initialize. Please try again.', 'wp-sms'));
        } finally {
            setInitializing(false);
        }
    }

    if (!authed) return null;

    const step = wizardStep.value;
    const showStepper = !initializing && !initError && !['resume', 'denied', 'unsupported', 'no_methods'].includes(step);
    const backupFactor = !initializing && !initError ? enrolledFactors.value.find((f) => f.channel_id === 'backup_codes') : null;

    return (
        <AuthLayout bare>
            {initializing && (
                <div className="wsms-auth-flex-center" style={{ minHeight: '20rem' }}>
                    <Spinner />
                </div>
            )}

            {initError && (
                <div className="wsms-auth-stack-4">
                    <Alert variant="destructive">{initError}</Alert>
                </div>
            )}

            {!initializing && !initError && (
                <WizardLayout showStepper={showStepper}>
                    {step === 'welcome' && (
                        <WelcomeStep
                            onStartSetup={wizard.handleStartSetup}
                            onSkip={wizard.handleSkip}
                            onDismissPrompt={wizard.handleDismissPrompt}
                            onSignOut={wizard.handleSignOut}
                            backupCodeInfo={backupFactor}
                        />
                    )}

                    {step === 'select' && (
                        <SelectMethodStep
                            onSelectMethod={wizard.handleSelectMethod}
                            onConfirm={wizard.handleConfirmMethod}
                            onBack={wizard.goBack}
                        />
                    )}

                    {step === 'enroll' && (
                        <EnrollStep
                            onEnroll={wizard.handleEnroll}
                            onVerify={wizard.handleVerify}
                            onPasskeyEnroll={wizard.handlePasskeyEnroll}
                            onBack={wizard.goBack}
                            goToStep={wizard.goToStep}
                        />
                    )}

                    {step === 'backup' && (
                        <BackupCodesStep onContinue={wizard.handleBackupContinue} />
                    )}

                    {step === 'success' && (
                        <SuccessStep onFinish={wizard.handleFinish} />
                    )}

                    {step === 'resume' && (
                        <SessionResumeStep
                            resumeChannel={resumeState?.channel}
                            onContinue={() => wizard.handleResumeContinue(resumeState?.step, resumeState?.channel)}
                            onStartOver={wizard.handleResumeStartOver}
                        />
                    )}

                    {step === 'denied' && (
                        <AccessDeniedStep onSignOut={wizard.handleSignOut} />
                    )}

                    {step === 'unsupported' && (
                        <UnsupportedStep onSignOut={wizard.handleSignOut} />
                    )}

                    {step === 'no_methods' && (
                        <NoMethodsStep onSignOut={wizard.handleSignOut} />
                    )}
                </WizardLayout>
            )}
        </AuthLayout>
    );
}
