import { signal, computed } from '@preact/signals';
import { api } from '../api/client';
import { isPreviewMode, brandingConfig } from './branding';

export const authConfig = signal(null);
export const configLoading = signal(false);
export const formSlug = signal(null);
/** @type {import('@preact/signals').Signal<'fullpage'|'popup'|'embed'>} */
export const renderMode = signal('fullpage');
export const primaryMethods = computed(() => authConfig.value?.primary_methods ?? ['password']);
export const methodDetails = computed(() => authConfig.value?.method_details ?? {});
export const registrationFields = computed(() => authConfig.value?.registration_fields ?? ['email', 'password']);
export const registrationFieldDefs = computed(() => authConfig.value?.registration_field_definitions ?? []);
export const profileFieldDefs = computed(() => authConfig.value?.profile_field_definitions ?? []);
export const captchaConfig = computed(() => authConfig.value?.captcha ?? null);
export const enabledChannels = computed(() => authConfig.value?.enabled_channels ?? []);
export const socialProviders = computed(() => authConfig.value?.social_providers ?? []);
export const legalLinks = computed(() => authConfig.value?.legal_links ?? null);
export const trustedDevicesConfig = computed(() => authConfig.value?.trusted_devices ?? null);
export const formRedirectUrl = computed(() => authConfig.value?.form_redirect_url ?? null);
export const formName = computed(() => authConfig.value?.form_name ?? null);

export async function loadConfig(fSlug = null, { force = false } = {}) {
    if (!force && (authConfig.value || configLoading.value)) return;

    configLoading.value = true;
    try {
        const params = fSlug ? `?form=${encodeURIComponent(fSlug)}` : '';
        const data = await api.get(`/auth/config${params}`);
        authConfig.value = data;

        if (!isPreviewMode.value && data.branding) {
            brandingConfig.value = data.branding;
        }
    } finally {
        configLoading.value = false;
    }
}
