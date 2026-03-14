import { signal, computed } from '@preact/signals';
import { api } from '../api/client';

export const authConfig = signal(null);
export const configLoading = signal(false);
export const primaryMethods = computed(() => authConfig.value?.primary_methods ?? ['password']);
export const methodDetails = computed(() => authConfig.value?.method_details ?? {});
export const registrationFields = computed(() => authConfig.value?.registration_fields ?? ['email', 'password']);
export const captchaConfig = computed(() => authConfig.value?.captcha ?? null);
export const enabledChannels = computed(() => authConfig.value?.enabled_channels ?? []);
export const socialProviders = computed(() => authConfig.value?.social_providers ?? []);

export async function loadConfig() {
    if (authConfig.value || configLoading.value) return;
    configLoading.value = true;
    try {
        const data = await api.get('/auth/config');
        authConfig.value = data;
    } finally {
        configLoading.value = false;
    }
}
