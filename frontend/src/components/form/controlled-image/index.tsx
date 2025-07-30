import { Controller, useFormContext } from 'react-hook-form';
import { FieldWrapper } from '../field-wrapper';
import { useWordPressMediaUploader } from '@/core/hooks';
import type { ControlledImageProps } from './types';
import { Button } from '@/components/ui/button';
import { CloudUpload } from 'lucide-react';

export const ControlledImage: React.FC<ControlledImageProps> = ({
    name,
    label,
    description,
    tooltip,
    tag,
    isLocked,
    isLoading,
}) => {
    const { openMediaUploader } = useWordPressMediaUploader();

    const { control } = useFormContext();

    return (
        <Controller
            control={control}
            name={name}
            render={({ field, fieldState }) => {
                return (
                    <FieldWrapper
                        label={label}
                        description={description}
                        isLoading={isLoading}
                        error={fieldState?.error?.message}
                        isLocked={isLocked}
                        tag={tag}
                        tooltip={tooltip}
                    >
                        <Button variant="outline" type="button" onClick={() => openMediaUploader(field.onChange)}>
                            <CloudUpload />

                            {field.value ? 'Change Image' : 'Select Image'}
                        </Button>
                    </FieldWrapper>
                );
            }}
        />
    );
};
