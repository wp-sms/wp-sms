import { useCallback } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import {
    wizardStep, selectedChannel, enrollmentData, backupCodes,
    availableMethods, wizardError, wizardLoading, wizardDirection,
    singleMethodMode,
    saveResumeState, clearResumeState, resetWizard,
} from '../signals/enrollment';
import { enrollChannel, verifyEnrollment, startPasskeyRegistration, dismissMfaPrompt, extractError, formatWebAuthnError } from '../utils/mfa-enrollment';
import { refreshUser } from '../signals/user';
import { redirectTo } from '../utils/auth';
import { getBaseUrl } from '../utils/urls';

export function useEnrollmentWizard() {
    const goToStep = useCallback((step, direction = 'forward') => {
        wizardDirection.value = direction;
        wizardStep.value = step;
        wizardError.value = null;
        saveResumeState();
    }, []);

    const goBack = useCallback(() => {
        const step = wizardStep.value;
        if (step === 'enroll') {
            enrollmentData.value = null;
            goToStep(singleMethodMode.value ? 'welcome' : 'select', 'back');
        } else if (step === 'select') {
            goToStep('welcome', 'back');
        }
    }, [goToStep]);

    const handleSkip = useCallback(() => {
        clearResumeState();
        redirectTo(getBaseUrl());
    }, []);

    const handleDismissPrompt = useCallback(async () => {
        try {
            await dismissMfaPrompt();
            clearResumeState();
            redirectTo(getBaseUrl());
        } catch (err) {
            wizardError.value = extractError(err);
        }
    }, []);

    const handleSelectMethod = useCallback((channelId) => {
        selectedChannel.value = channelId;
    }, []);

    const handleConfirmMethod = useCallback(() => {
        if (!selectedChannel.value) return;
        goToStep('enroll');
    }, [goToStep]);

    const handleStartSetup = useCallback(() => {
        if (singleMethodMode.value) {
            selectedChannel.value = availableMethods.value[0].id;
            goToStep('enroll');
        } else {
            goToStep('select');
        }
    }, [goToStep]);

    const handleEnroll = useCallback(async (channelId, data = {}) => {
        wizardError.value = null;
        wizardLoading.value = true;

        try {
            const res = await enrollChannel(channelId, data);

            if (!res.success) {
                wizardError.value = { message: res.message || __('Enrollment failed.', 'wp-sms') };
                return null;
            }

            // Channel requires confirmation (OTP, passkey attestation, etc.)
            if (res.data?.requires_confirmation || res.data?.requires_verification) {
                enrollmentData.value = res.data;
                return res;
            }

            await refreshUser();

            if (res.data?.backup_codes) {
                backupCodes.value = res.data.backup_codes;
                goToStep('backup');
            } else {
                goToStep('success');
            }

            return res;
        } catch (err) {
            wizardError.value = extractError(err);
            return null;
        } finally {
            wizardLoading.value = false;
        }
    }, [goToStep]);

    const handleVerify = useCallback(async (channelId, code) => {
        wizardError.value = null;
        wizardLoading.value = true;

        try {
            const res = await verifyEnrollment(channelId, code);

            if (!res.success) {
                wizardError.value = {
                    message: res.message || __('Verification failed.', 'wp-sms'),
                    code: res.error,
                    retryAfter: res.meta?.retry_after,
                };
                return false;
            }

            enrollmentData.value = null;
            await refreshUser();

            if (res.data?.backup_codes) {
                backupCodes.value = res.data.backup_codes;
                goToStep('backup');
            } else {
                goToStep('success');
            }

            return true;
        } catch (err) {
            const details = extractError(err);
            wizardError.value = details;
            return false;
        } finally {
            wizardLoading.value = false;
        }
    }, [goToStep]);

    const handlePasskeyEnroll = useCallback(async () => {
        wizardError.value = null;
        wizardLoading.value = true;

        try {
            const res = await enrollChannel('passkey');

            if (!res.success || !res.data?.creation_options) {
                wizardError.value = { message: res.message || __('Could not start passkey setup.', 'wp-sms') };
                return;
            }

            enrollmentData.value = res.data;
            const attestation = await startPasskeyRegistration(res.data.creation_options);
            const verifyRes = await verifyEnrollment('passkey', attestation);

            if (verifyRes.success) {
                enrollmentData.value = null;
                await refreshUser();

                if (verifyRes.data?.backup_codes) {
                    backupCodes.value = verifyRes.data.backup_codes;
                    goToStep('backup');
                } else {
                    goToStep('success');
                }
            } else {
                wizardError.value = { message: verifyRes.message || __('Passkey verification failed.', 'wp-sms') };
            }
        } catch (err) {
            if (err.name === 'NotAllowedError' || err.name === 'InvalidStateError') {
                wizardError.value = { message: formatWebAuthnError(err) };
            } else {
                wizardError.value = extractError(err);
            }
        } finally {
            wizardLoading.value = false;
        }
    }, [goToStep]);

    const handleBackupContinue = useCallback(() => {
        goToStep('success');
        clearResumeState();
    }, [goToStep]);

    const handleFinish = useCallback(() => {
        clearResumeState();
        redirectTo(getBaseUrl());
    }, []);

    const handleResumeStartOver = useCallback(() => {
        resetWizard();
        goToStep('welcome');
    }, [goToStep]);

    const handleResumeContinue = useCallback((resumeStep, resumeChannel) => {
        if (resumeChannel) selectedChannel.value = resumeChannel;
        goToStep(resumeStep || 'enroll');
    }, [goToStep]);

    const handleSignOut = useCallback(async () => {
        const { logout } = await import('../utils/auth');
        await logout();
    }, []);

    return {
        goToStep,
        goBack,
        handleSkip,
        handleDismissPrompt,
        handleSelectMethod,
        handleConfirmMethod,
        handleStartSetup,
        handleEnroll,
        handleVerify,
        handlePasskeyEnroll,
        handleBackupContinue,
        handleFinish,
        handleResumeStartOver,
        handleResumeContinue,
        handleSignOut,
    };
}
