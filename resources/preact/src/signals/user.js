import { signal, computed } from '@preact/signals';
import { api } from '../api/client';
import { currentUser } from './auth';

export const userLoading = signal(false);

export const mfaEnabled = computed(() => currentUser.value?.mfa_enabled ?? false);
export const enrolledFactors = computed(() => currentUser.value?.enrolled_factors ?? []);

export async function loadCurrentUser() {
    if (!window.wsmsAuth?.isLoggedIn) return;
    if (userLoading.value) return;
    userLoading.value = true;

    try {
        const res = await api.get('/auth/me');
        currentUser.value = res.user;
    } catch {
        currentUser.value = null;
    } finally {
        userLoading.value = false;
    }
}

export async function refreshUser() {
    userLoading.value = true;
    try {
        const res = await api.get('/auth/me');
        currentUser.value = res.user;
    } catch {
        // keep current state
    } finally {
        userLoading.value = false;
    }
}
