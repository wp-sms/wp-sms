import { isPreviewMode } from '../signals/branding';
import { AuthLayout } from '../layouts/AuthLayout';

/**
 * Returns "Already Signed In" view when the user is logged in outside of
 * preview mode, or null otherwise.  Call after all hooks to avoid violations.
 */
export function alreadySignedIn() {
    if (isPreviewMode.value || !Number(window.wsmsAuth?.isLoggedIn)) return null;
    return (
        <AuthLayout title="Already Signed In">
            <p className="text-center text-sm text-muted-foreground">
                You are already logged in.
            </p>
        </AuthLayout>
    );
}
