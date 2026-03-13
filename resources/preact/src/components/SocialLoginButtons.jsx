import { socialProviders } from '../signals/config';
import { Button } from './ui/Button';

export function SocialLoginButtons({ intent = 'login' }) {
    const providers = socialProviders.value;

    if (!providers.length) return null;

    return (
        <div className="space-y-2">
            {providers.map((provider) => (
                <Button
                    key={provider.id}
                    variant="outline"
                    className="w-full"
                    type="button"
                    onClick={() => {
                        const url = new URL(provider.authorize_url, window.location.origin);
                        url.searchParams.set('intent', intent);
                        window.location.href = url.toString();
                    }}
                >
                    <span
                        className="mr-2 inline-flex"
                        dangerouslySetInnerHTML={{ __html: provider.icon }}
                    />
                    Continue with {provider.name}
                </Button>
            ))}
        </div>
    );
}
