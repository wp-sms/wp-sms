import { Label } from '@/components/ui/label';
import type { FieldLabelProps } from './types';
import clsx from 'clsx';

export const FieldLabel: React.FC<FieldLabelProps> = ({ text, htmlFor, isInvalid = false }) => {
    if (!text) {
        return null;
    }

    return (
        <Label className={clsx('text-xs font-normal', isInvalid && 'text-destructive')} htmlFor={htmlFor}>
            {text}
        </Label>
    );
};
