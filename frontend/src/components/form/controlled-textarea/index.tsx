import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledTextareaProps } from './types';
import { Textarea } from '@/components/ui/textarea';

export const ControlledTextarea: React.FC<ControlledTextareaProps> = ({ ...props }) => {
    const { control } = useFormContext();

    return (
        <Controller
            name={props?.name ?? ''}
            control={control}
            render={({ field, fieldState }) => {
                return (
                    <>
                        <Textarea aria-invalid={fieldState.invalid} {...field} {...props} />
                        {fieldState.error && <p className="text-red-500 text-xs mt-1">{fieldState.error.message}</p>}
                    </>
                );
            }}
        />
    );
};
