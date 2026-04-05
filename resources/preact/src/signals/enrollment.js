import { signal, computed } from '@preact/signals';

// Core wizard state
export const wizardStep = signal('welcome');       // 'welcome'|'select'|'enroll'|'backup'|'success'|'resume'|'denied'|'unsupported'|'no_methods'
export const wizardMode = signal('forced');         // 'forced'|'grace'|'voluntary'|'re-enroll'
export const selectedChannel = signal(null);        // 'totp'|'phone'|'passkey'|'email'|'telegram'|'line'
export const enrollmentData = signal(null);         // Channel-specific: { qrCodeUri, secret, deepLink, creationOptions }
export const backupCodes = signal(null);            // string[] from enrollment response
export const availableMethods = signal([]);         // From GET /auth/methods (filtered supports_mfa)
export const wizardError = signal(null);            // { message, code, retryAfter }
export const wizardLoading = signal(false);
export const wizardDirection = signal('forward');   // For StepTransition animation
export const codesSaved = signal(false);            // Checkbox on backup step
export const gracePeriodInfo = signal(null);        // { remaining_days, remaining_hours, expires_at }

// Computed
export const singleMethodMode = computed(() => availableMethods.value.length === 1);

export const stepLabels = computed(() => {
    if (singleMethodMode.value) {
        return ['Setup', 'Verify', 'Backup'];
    }
    return ['Setup', 'Method', 'Verify', 'Backup'];
});

export const currentStepIndex = computed(() => {
    const step = wizardStep.value;
    if (step === 'welcome') return 0;

    if (singleMethodMode.value) {
        if (step === 'enroll') return 1;
        if (step === 'backup') return 2;
        if (step === 'success') return 3;
    } else {
        if (step === 'select') return 1;
        if (step === 'enroll') return 2;
        if (step === 'backup') return 3;
        if (step === 'success') return 4;
    }

    // Non-stepper steps (resume, denied, unsupported, no_methods)
    return -1;
});

const STORAGE_KEY = 'wsms_enrollment_state';
const STALE_MINUTES = 30;

/**
 * Save current wizard state to sessionStorage for resume.
 */
export function saveResumeState() {
    const state = {
        step: wizardStep.value,
        channel: selectedChannel.value,
        timestamp: Date.now(),
    };
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

/**
 * Load resume state from sessionStorage.
 *
 * @param {string[]} availableChannelIds - Currently available channel IDs
 * @returns {{ step: string, channel: string }|null} Resume state or null if stale/invalid
 */
export function loadResumeState(availableChannelIds) {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;

    try {
        const state = JSON.parse(raw);
        const ageMinutes = (Date.now() - state.timestamp) / 60000;

        if (ageMinutes > STALE_MINUTES) {
            clearResumeState();
            return null;
        }

        // Discard if channel is no longer available
        if (state.channel && !availableChannelIds.includes(state.channel)) {
            clearResumeState();
            return null;
        }

        return { step: state.step, channel: state.channel };
    } catch {
        clearResumeState();
        return null;
    }
}

export function clearResumeState() {
    sessionStorage.removeItem(STORAGE_KEY);
}

/**
 * Reset all wizard signals to initial state.
 */
export function resetWizard() {
    wizardStep.value = 'welcome';
    selectedChannel.value = null;
    enrollmentData.value = null;
    backupCodes.value = null;
    wizardError.value = null;
    wizardLoading.value = false;
    wizardDirection.value = 'forward';
    codesSaved.value = false;
    clearResumeState();
}
