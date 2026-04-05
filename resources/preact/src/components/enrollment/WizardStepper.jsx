import { __ } from '@wordpress/i18n';
import { Check } from 'lucide-react';
import { cn } from '@/utils/cn';

export function WizardStepper({ labels, currentIndex }) {
    return (
        <div className="wsms-auth-stepper" role="navigation" aria-label={__('Setup progress', 'wp-sms')}>
            {labels.map((label, i) => {
                const done = i < currentIndex;
                const active = i === currentIndex;
                return (
                    <Fragment key={i}>
                        {i > 0 && (
                            <div className={cn('wsms-auth-stepper__line', done && 'wsms-auth-stepper__line--done')} />
                        )}
                        <div
                            className={cn(
                                'wsms-auth-stepper__step',
                                active && 'wsms-auth-stepper__step--active',
                                done && 'wsms-auth-stepper__step--done',
                            )}
                            aria-current={active ? 'step' : undefined}
                        >
                            <div className="wsms-auth-stepper__dot">
                                {done ? <Check size={12} /> : i + 1}
                            </div>
                            <span className="wsms-auth-stepper__label">{label}</span>
                        </div>
                    </Fragment>
                );
            })}
        </div>
    );
}

function Fragment({ children }) {
    return children;
}
