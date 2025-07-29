import { Button } from '@/components/ui/button';
import { getGroupValuesOptions, useSaveSettingsValues } from '@/models/settings';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { useFormContext } from 'react-hook-form';
import { useParams } from 'react-router-dom';
import { toast } from 'sonner';

export const SettingsFormActions: React.FC = () => {
    const { formState, reset, handleSubmit, setValue } = useFormContext();

    const { name } = useParams();

    const queryClient = useQueryClient();

    const saveSettings = useSaveSettingsValues({
        onSuccess: (response, variables) => {
            for (const field in variables) {
                queryClient.setQueryData(
                    getGroupValuesOptions({ params: { groupName: name ?? 'general' } }).queryKey,
                    (old: any) => {
                        return {
                            ...old,
                            [field]: variables[field],
                        };
                    }
                );

                setValue(field, variables[field]);
            }

            toast.success('Settings saved successfully', {
                position: 'top-center',
                className: '!p-4',
            });
        },
    });

    const handleSave = useCallback(
        async (values: Record<string, any>) => {
            const valuesToSave = Object.keys(formState.dirtyFields).reduce((acc: Record<string, any>, key) => {
                if (values.hasOwnProperty(key)) {
                    acc[key] = values[key];
                }
                return acc;
            }, {});

            try {
                await saveSettings.mutateAsync(valuesToSave);
                reset(values);
            } catch (error) {
                toast.error('Failed to save settings');
            }
        },
        [formState.dirtyFields, saveSettings, reset]
    );

    const handleReset = useCallback(() => {
        reset(formState.defaultValues);
    }, [reset, formState]);

    return (
        <div className="flex items-center gap-x-3 sticky bottom-0 bg-background p-3 z-50 mt-2">
            <Button disabled={!formState.isDirty} type="submit" onClick={handleSubmit(handleSave)}>
                Save Changes
            </Button>

            <Button disabled={!formState.isDirty} type="reset" variant="secondary" onClick={handleReset}>
                Reset
            </Button>
        </div>
    );
};
