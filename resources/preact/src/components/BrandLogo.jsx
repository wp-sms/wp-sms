import { useState } from 'preact/hooks';
import { Logo } from './Logo';
import { brandingConfig } from '../signals/branding';

/**
 * Displays the custom logo or falls back to the default WSMS SVG icon.
 * Shows site_name text beside the logo.
 *
 * @param {{ size?: 'sm' | 'md', className?: string }} props
 */
export function BrandLogo({ size = 'md', className = '' }) {
    const config = brandingConfig.value;
    const logoUrl = config?.logo_url;
    const siteName = config?.site_name || 'WSMS';
    const logoSize = config?.logo_size ?? 40;
    const [imgError, setImgError] = useState(false);

    const textSize = size === 'sm' ? 'text-sm' : 'text-lg';
    const textHidden = size === 'sm' ? 'hidden sm:inline' : '';

    // For sm variant (account nav), cap logo to 28px
    const effectiveSize = size === 'sm' ? Math.min(logoSize, 28) : logoSize;

    if (logoUrl && !imgError) {
        return (
            <div className={`flex items-center gap-2 ${className}`}>
                <img
                    src={logoUrl}
                    alt={siteName}
                    style={{ maxHeight: `${effectiveSize}px`, maxWidth: '160px' }}
                    className="object-contain"
                    onError={() => setImgError(true)}
                />
                <span className={`${textSize} font-semibold tracking-tight ${textHidden}`}>
                    {siteName}
                </span>
            </div>
        );
    }

    // Default WSMS icon — size scales with logo_size
    const iconPx = size === 'sm' ? 28 : Math.max(28, Math.min(effectiveSize, 48));
    const innerScale = iconPx <= 32 ? 0.5 : 0.55;

    return (
        <div className={`flex items-center gap-2 ${className}`}>
            <div
                className="flex items-center justify-center rounded-lg bg-primary"
                style={{ width: `${iconPx}px`, height: `${iconPx}px` }}
            >
                <Logo
                    className="text-primary-foreground"
                    style={{ width: `${iconPx * innerScale}px`, height: `${iconPx * innerScale}px` }}
                />
            </div>
            <span className={`${textSize} font-semibold tracking-tight ${textHidden}`}>
                {siteName}
            </span>
        </div>
    );
}
