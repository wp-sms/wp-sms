import { socialProviders } from '../signals/config';
import { Button } from './ui/Button';

export function SocialLoginButtons() {
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
                        window.location.href = provider.authorize_url;
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
