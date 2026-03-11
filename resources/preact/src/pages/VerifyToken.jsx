import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
import { authUrl, getQueryParam } from '../utils/urls';
import { Alert } from '../components/Alert';

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
        <div class="wsms-page">
            <h1 class="wsms-title">{title}</h1>

            {status === 'loading' && (
                <div class="wsms-loader">
                    <div class="wsms-spinner" />
                    <p class="wsms-subtitle">{loadingText}</p>
                </div>
            )}

            {status === 'success' && (
                <>
                    <Alert type="success" message={message} />
                    {!successRedirect && (
                        <div class="wsms-links">
                            <a href={authUrl('/login')} class="wsms-link">
                                {successLinkText || 'Back to login'}
                            </a>
                        </div>
                    )}
                </>
            )}

            {status === 'error' && (
                <>
                    <Alert type="error" message={message} />
                    <div class="wsms-links">
                        <a href={authUrl('/login')} class="wsms-link">Back to login</a>
                    </div>
                </>
            )}
        </div>
    );
}
