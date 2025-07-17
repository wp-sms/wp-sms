import { Input } from '@/components/ui/input';
import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledInputProps } from './types';

export const ControlledInput: React.FC<ControlledInputProps> = ({ ...props }) => {
    const { control } = useFormContext();

    return (
        <Controller
            name={props?.name ?? ''}
            control={control}
            render={({ field }) => {
                return <Input {...field} {...props} />;
            }}
        />
    );
};
