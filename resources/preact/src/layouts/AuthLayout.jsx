import { brandingConfig } from '@/signals/branding';
import { CenteredLayout } from './CenteredLayout';
import { SplitLayout } from './SplitLayout';

export function AuthLayout(props) {
    const layout = brandingConfig.value?.layout ?? 'centered';
    return layout === 'split' ? <SplitLayout {...props} /> : <CenteredLayout {...props} />;
}
