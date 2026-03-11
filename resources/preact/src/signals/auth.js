import { signal, computed } from '@preact/signals';

export const currentUser = signal(null);
export const isAuthenticated = computed(() => currentUser.value !== null);
export const challengeToken = signal(null);
export const challengeMeta = signal(null);
export const pendingMfa = signal(null);
export const authError = signal(null);
export const authLoading = signal(false);

export function clearAuth() {
    challengeToken.value = null;
    challengeMeta.value = null;
    pendingMfa.value = null;
    authError.value = null;
    authLoading.value = false;
}
