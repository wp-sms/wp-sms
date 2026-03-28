import { __ } from '@wordpress/i18n';
import { isPreviewMode } from '../signals/branding';
import { AuthLayout } from '../layouts/AuthLayout';

/**
 * Returns "Already Signed In" view when the user is logged in outside of
 * preview mode, or null otherwise.  Call after all hooks to avoid violations.
 */
export function alreadySignedIn() {
    if (isPreviewMode.value || !Number(window.wsmsAuth?.isLoggedIn)) return null;
    return (
        <AuthLayout title={__('Already Signed In', 'wp-sms')}>
            <p className="wsms-auth-center wsms-auth-text-sm wsms-auth-text-muted">
                {__('You are already logged in.', 'wp-sms')}
            </p>
        </AuthLayout>
    );
}
