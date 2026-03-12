import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
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
            setMessage('Invalid or missing token.');
            return;
        }

        api.post(endpoint, { token })
            .then((res) => {
                if (res.success || res.status === 'authenticated') {
                    setStatus('success');
                    setMessage(res.message || successText || 'Verified!');

                    if (successRedirect) {
                        setTimeout(() => {
                            window.location.href = res.redirect || authUrl('/');
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
    }, []);

    return (
        <AuthLayout
            title={title}
            footer={
                status !== 'loading' && !successRedirect && (
                    <AuthLink href={authUrl('/login')}>
                        {successLinkText || 'Back to login'}
                    </AuthLink>
                )
            }
        >
            {status === 'loading' && (
                <div className="flex flex-col items-center gap-3 py-4">
                    <Spinner className="size-8" />
                    <p className="text-sm text-muted-foreground">{loadingText}</p>
                </div>
            )}

            {status === 'success' && <Alert variant="success" message={message} />}
            {status === 'error' && <Alert variant="destructive" message={message} />}
        </AuthLayout>
    );
}
