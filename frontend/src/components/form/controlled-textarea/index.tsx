import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledTextareaProps } from './types';
import { Textarea } from '@/components/ui/textarea';

export const ControlledTextarea: React.FC<ControlledTextareaProps> = ({ ...props }) => {
    const { control } = useFormContext();

    return (
        <Controller
            name={props?.name ?? ''}
            control={control}
            render={({ field }) => {
                return <Textarea {...field} {...props} />;
            }}
        />
    );
};
