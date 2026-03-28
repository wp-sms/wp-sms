import { socialProviders } from '../signals/config';
import { brandingConfig } from '../signals/branding';
import { SocialLoginButtons } from './SocialLoginButtons';
import { SocialDivider } from './SocialDivider';

export function SocialBlock({ intent }) {
    const hasSocial = socialProviders.value.length > 0;
    if (!hasSocial) return null;

    const pos = brandingConfig.value?.social_position ?? 'top';
    return pos === 'top' ? (
        <>
            <SocialLoginButtons intent={intent} />
            <SocialDivider />
        </>
    ) : (
        <>
            <SocialDivider />
            <SocialLoginButtons intent={intent} />
        </>
    );
}
