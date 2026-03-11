import { useEffect } from 'preact/hooks';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, userLoading } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { authUrl } from '../utils/urls';
import { logout } from '../utils/auth';

export function Account() {
    const authed = useAuthGuard();

    useEffect(() => {
        if (authed) loadCurrentUser();
    }, [authed]);

    if (!authed) return null;

    if (userLoading.value && !currentUser.value) {
        return (
            <div class="wsms-page">
                <div class="wsms-loader">
                    <div class="wsms-spinner" />
                    <p class="wsms-subtitle">Loading your account\u2026</p>
                </div>
            </div>
        );
    }

    const user = currentUser.value;
    if (!user) return null;

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Welcome, {user.display_name || user.username}</h1>
            <p class="wsms-subtitle">{user.email}</p>

            <nav class="wsms-account-nav">
                <a href={authUrl('/profile')} class="wsms-account-nav__item">
                    <span class="wsms-account-nav__icon">{'\u{1F464}'}</span>
                    <div>
                        <strong>Profile</strong>
                        <p>Update your name, email, and phone number</p>
                    </div>
                </a>
                <a href={authUrl('/security')} class="wsms-account-nav__item">
                    <span class="wsms-account-nav__icon">{'\u{1F6E1}\u{FE0F}'}</span>
                    <div>
                        <strong>Security</strong>
                        <p>
                            {user.mfa_enabled
                                ? `MFA enabled (${user.enrolled_factors.length} factor${user.enrolled_factors.length !== 1 ? 's' : ''})`
                                : 'Set up multi-factor authentication'}
                        </p>
                    </div>
                </a>
                <a href={authUrl('/change-password')} class="wsms-account-nav__item">
                    <span class="wsms-account-nav__icon">{'\u{1F511}'}</span>
                    <div>
                        <strong>Change Password</strong>
                        <p>Update your password</p>
                    </div>
                </a>
            </nav>

            <div class="wsms-links">
                <button type="button" class="wsms-btn wsms-btn--text wsms-btn--danger" onClick={logout}>
                    Sign Out
                </button>
            </div>
        </div>
    );
}
