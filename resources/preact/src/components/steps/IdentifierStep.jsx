import { useState, useEffect } from 'preact/hooks';
import { api } from '../../api/client';
import { primaryMethods } from '../../signals/config';
import {
    authError,
    authLoading,
    authStep,
    identifyResult,
    enteredIdentifier,
    rememberedIdentifier,
    rememberIdentifier,
    forgetIdentifier,
} from '../../signals/auth';
import { extractError } from '../../utils/auth';
import { socialProviders } from '../../signals/config';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Label } from '../ui/Label';
import { SocialLoginButtons } from '../SocialLoginButtons';
import { SocialDivider } from '../SocialDivider';

function getIdentifierHints(methods) {
    const hasPhone = methods.includes('phone');
    const hasEmail = methods.includes('email');
    const hasPassword = methods.includes('password');

    const parts = [];
    if (hasEmail || hasPassword) parts.push('Email');
    if (hasPhone) parts.push('phone');
    if (hasPassword) parts.push('username');

    if (parts.length === 0) return { label: 'Email or username', placeholder: 'you@example.com' };

    const label = parts.length === 1
        ? parts[0]
        : parts.slice(0, -1).join(', ') + ' or ' + parts[parts.length - 1];

    const placeholder = (hasEmail || hasPassword) ? 'you@example.com' : hasPhone ? '+1234567890' : '';

    return { label, placeholder };
}

export function IdentifierStep() {
    const [identifier, setIdentifier] = useState(rememberedIdentifier.value || '');
    const remembered = rememberedIdentifier.value;
    const methods = primaryMethods.value;
    const { label: identifierLabel, placeholder } = getIdentifierHints(methods);

    // Auto-identify for returning users.
    useEffect(() => {
        if (remembered) {
            doIdentify(remembered);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    async function doIdentify(id) {
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/identify', { identifier: id });
            enteredIdentifier.value = id;
            identifyResult.value = res;

            if (res.user_found) {
                rememberIdentifier(id);
                authStep.value = 'authenticate';
            } else if (res.registration_available) {
                authStep.value = 'register';
            } else {
                authError.value = 'No account found with this identifier.';
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (!identifier.trim()) return;
        doIdentify(identifier.trim());
    }

    function handleNotYou() {
        forgetIdentifier();
        setIdentifier('');
        authError.value = null;
    }

    // If we have a remembered identifier, show a returning user view while loading.
    if (remembered && authLoading.value) {
        return (
            <div className="space-y-4 text-center">
                <p className="text-sm text-muted-foreground">
                    Signing in as <strong>{remembered}</strong>...
                </p>
            </div>
        );
    }

    const hasSocial = socialProviders.value.length > 0;

    return (
        <div className="space-y-4">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            {hasSocial && <SocialLoginButtons />}
            {hasSocial && <SocialDivider />}

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                    <Label for="wsms-identifier">{identifierLabel}</Label>
                    <Input
                        id="wsms-identifier"
                        type="text"
                        value={identifier}
                        onInput={(e) => setIdentifier(e.target.value)}
                        placeholder={placeholder}
                        required
                        disabled={authLoading.value}
                        autoComplete="username"
                        autoFocus
                    />
                </div>
                <Button className="w-full" type="submit" disabled={authLoading.value || !identifier.trim()}>
                    {authLoading.value ? 'Checking...' : 'Continue'}
                </Button>
            </form>

            {remembered && !authLoading.value && (
                <div className="text-center">
                    <Button variant="link" type="button" onClick={handleNotYou}>
                        Not you? Use a different account
                    </Button>
                </div>
            )}
        </div>
    );
}
