import { __ } from '@wordpress/i18n';
import { cn } from '@/utils/cn';
import { getChannelMeta } from '../../utils/channel-meta';

export function MethodRadioCard({ method, selected, disabled, disabledReason, recommended, onSelect }) {
    const meta = getChannelMeta(method.id, method);
    const Icon = meta.icon;

    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            aria-disabled={disabled}
            disabled={disabled}
            className={cn(
                'wsms-auth-mfa-card',
                selected && 'wsms-auth-mfa-card--selected',
                disabled && 'wsms-auth-mfa-card--disabled',
            )}
            onClick={() => !disabled && onSelect(method.id)}
        >
            <div className="wsms-auth-mfa-card__header">
                <div className="wsms-auth-mfa-card__radio">
                    <div className={cn('wsms-auth-radio', selected && 'wsms-auth-radio--checked')} />
                </div>
                {meta.iconSvg
                    ? <span className="wsms-auth-mfa-card__icon" dangerouslySetInnerHTML={{ __html: meta.iconSvg }} aria-hidden="true" />
                    : Icon && <Icon className="wsms-auth-mfa-card__icon" />}
                <div className="wsms-auth-mfa-card__info">
                    <div className="wsms-auth-mfa-card__name">
                        {meta.label}
                        {recommended && (
                            <span className="wsms-auth-badge wsms-auth-badge--primary">
                                {__('Recommended', 'wp-sms')}
                            </span>
                        )}
                    </div>
                    <div className="wsms-auth-mfa-card__desc">
                        {disabled && disabledReason ? disabledReason : meta.description}
                    </div>
                </div>
            </div>
        </button>
    );
}
