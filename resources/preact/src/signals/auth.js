import { signal, computed } from '@preact/signals';

export const currentUser = signal(null);
export const isAuthenticated = computed(() => currentUser.value !== null);
export const challengeToken = signal(null);
export const challengeMeta = signal(null);
export const pendingMfa = signal(null);
export const authError = signal(null);
export const authLoading = signal(false);

// Identifier-first flow signals.
export const authStep = signal('identifier'); // 'identifier'|'authenticate'|'mfa'|'register'
export const identifyResult = signal(null);
export const enteredIdentifier = signal('');
export const rememberedIdentifier = signal(localStorage.getItem('wsms_remembered_id') || '');
export const selectedMethod = signal(null);

export function rememberIdentifier(id) {
    localStorage.setItem('wsms_remembered_id', id);
    rememberedIdentifier.value = id;
}

export function forgetIdentifier() {
    localStorage.removeItem('wsms_remembered_id');
    rememberedIdentifier.value = '';
}

export function resetIdentifyFlow() {
    authStep.value = 'identifier';
    identifyResult.value = null;
    enteredIdentifier.value = '';
    selectedMethod.value = null;
    authError.value = null;
}

export function clearAuth() {
    challengeToken.value = null;
    challengeMeta.value = null;
    pendingMfa.value = null;
    authError.value = null;
    authLoading.value = false;
    resetIdentifyFlow();
}
