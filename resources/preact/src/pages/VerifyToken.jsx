import { useState, useEffect } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { api } from '../api/client';
import { redirectTo } from '../utils/auth';
import { authUrl, getQueryParam } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Spinner } from '../components/ui/Spinner';
import { AuthLink } from '../components/AuthLink';

export function VerifyToken({
    title,
    endpoint,
    loadingText,
    errorText,
    successText,
    successLinkText,
    successRedirect,
}) {
    const [status, setStatus] = useState('loading');
    const [message, setMessage] = useState('');

    useEffect(() => {
        const token = getQueryParam('token');

        if (!token) {
            setStatus('error');
            setMessage(__('Invalid or missing token.', 'wp-sms'));
            return;
        }

        api.post(endpoint, { token })
            .then((res) => {
                if (res.success || res.status === 'authenticated') {
                    setStatus('success');
                    setMessage(res.message || successText || __('Verified!', 'wp-sms'));

                    if (successRedirect) {
                        setTimeout(() => {
                            redirectTo(res.redirect || authUrl('/'));
                        }, 1000);
                    }
                } else {
                    setStatus('error');
                    setMessage(res.message || errorText);
                }
            })
            .catch((err) => {
                setStatus('error');
                setMessage(err.message || errorText);
            });
    }, []); // eslint-disable-line react-hooks/exhaustive-deps -- fire-once on mount, props are static

    return (
        <AuthLayout
            title={title}
            footer={
                status !== 'loading' && !successRedirect && (
                    <AuthLink href={authUrl('/login')}>
                        {successLinkText || __('Back to login', 'wp-sms')}
                    </AuthLink>
                )
            }
        >
            {status === 'loading' && (
                <div className="wsms-auth-loading-center" style={{ paddingTop: '1rem', paddingBottom: '1rem' }}>
                    <Spinner className="wsms-auth-spinner--lg" />
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">{loadingText}</p>
                </div>
            )}

            {status === 'success' && <Alert variant="success" message={message} />}
            {status === 'error' && <Alert variant="destructive" message={message} />}
        </AuthLayout>
    );
}
