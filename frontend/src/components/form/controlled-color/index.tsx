import { Controller, useFormContext } from 'react-hook-form';
import type { ControlledColorProps } from './types';
import { Input } from '@/components/ui/input';

export const ControlledColor: React.FC<ControlledColorProps> = ({ name, label, description, isLoading }) => {
    const { control } = useFormContext();

    return (
        <Controller
            name={name ?? ''}
            control={control}
            render={({ field, fieldState }) => {
                return (
                    <div className="flex items-center gap-2">
                        <Input type="color" {...field} className="w-12 h-10 p-1 border rounded cursor-pointer" />

                        <Input type="text" {...field} className="flex-1" placeholder="#ff6b35" />
                    </div>
                );
            }}
        />
    );
};
