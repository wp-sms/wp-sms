import { __ } from '@wordpress/i18n';
import { cn } from '@/utils/cn';
import { StepTransition } from '../ui/StepTransition';
import { WizardStepper } from './WizardStepper';
import { Alert } from '../ui/Alert';
import { wizardStep, wizardDirection, stepLabels, currentStepIndex, wizardError } from '../../signals/enrollment';

export function WizardLayout({ children, showStepper = true, footer }) {
    const step = wizardStep.value;
    const error = wizardError.value;
    const index = currentStepIndex.value;
    const labels = stepLabels.value;
    const showStepperBar = showStepper && index >= 0;

    return (
        <div className="wsms-auth-card wsms-auth-wizard">
            {showStepperBar && (
                <div className="wsms-auth-card__header">
                    <WizardStepper labels={labels} currentIndex={index} />
                </div>
            )}

            <div className="wsms-auth-card__content">
                {error && (
                    <Alert
                        variant="destructive"
                        message={error.message}
                        onDismiss={() => { wizardError.value = null; }}
                        className="wsms-auth-mb-4"
                    />
                )}
                <StepTransition step={step} direction={wizardDirection.value}>
                    {children}
                </StepTransition>
            </div>

            {footer && (
                <div className="wsms-auth-card__footer">
                    {footer}
                </div>
            )}
        </div>
    );
}
