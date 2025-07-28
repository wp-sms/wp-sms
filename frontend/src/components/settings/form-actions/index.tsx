import { Button } from '@/components/ui/button';
import { useCallback, useMemo } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';

export const SettingsFormActions: React.FC = () => {
    const { formState, reset } = useFormContext();

    const formValues = useWatch();

    const isFormDirty = useMemo(() => {
        const defaultValues = formState.defaultValues ?? {};

        for (const key in defaultValues) {
            const defaultValue = defaultValues?.[key];
            const currentValue = formValues[key];

            if ((defaultValue === null && currentValue === '') || (defaultValue === '' && currentValue === null)) {
                continue;
            }

            if (defaultValue !== currentValue) {
                return true;
            }
        }
        return false;
    }, [formState.defaultValues, formValues]);

    const handleReset = useCallback(() => {
        reset(formState.defaultValues);
    }, [reset, formState]);

    return (
        <div className="flex items-center gap-x-3">
            <Button disabled={!isFormDirty} type="submit">
                Save Changes
            </Button>

            <Button disabled={!isFormDirty} type="button" variant="secondary" onClick={handleReset}>
                Reset
            </Button>
        </div>
    );
};
