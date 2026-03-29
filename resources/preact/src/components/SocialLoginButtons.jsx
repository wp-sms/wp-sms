import { __, sprintf } from '@wordpress/i18n';
import { socialProviders } from '../signals/config';

function goToProvider(provider, intent) {
    const url = new URL(provider.authorize_url, window.location.origin);
    url.searchParams.set('intent', intent);
    window.location.href = url.toString();
}

export function SocialLoginButtons({ intent = 'login' }) {
    const providers = socialProviders.value;
    if (!providers.length) return null;

    const compact = providers.length >= 3;

    if (compact) {
        return (
            <div className="wsms-auth-social-row">
                {providers.map((provider) => (
                    <button
                        key={provider.id}
                        type="button"
                        className="wsms-auth-social-icon-btn"
                        aria-label={sprintf(__('Continue with %s', 'wp-sms'), provider.name)}
                        onClick={() => goToProvider(provider, intent)}
                    >
                        <span aria-hidden="true" dangerouslySetInnerHTML={{ __html: provider.icon }} />
                    </button>
                ))}
            </div>
        );
    }

    const layoutClass = providers.length === 2 ? 'wsms-auth-social-grid-2' : 'wsms-auth-stack-2';

    return (
        <div className={layoutClass}>
            {providers.map((provider) => (
                <button
                    key={provider.id}
                    type="button"
                    className="wsms-auth-social-btn"
                    onClick={() => goToProvider(provider, intent)}
                >
                    <span
                        className="wsms-auth-social-btn__icon"
                        aria-hidden="true"
                        dangerouslySetInnerHTML={{ __html: provider.icon }}
                    />
                    {provider.name}
                </button>
            ))}
        </div>
    );
}
