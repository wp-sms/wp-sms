import { Spinner } from './Spinner';

export function RedirectingOverlay() {
    return (
        <div className="wsms-auth-loading-center wsms-auth-fade-in">
            <Spinner className="wsms-auth-spinner--lg" />
            <p className="wsms-auth-text-sm wsms-auth-text-muted">Redirecting…</p>
        </div>
    );
}
