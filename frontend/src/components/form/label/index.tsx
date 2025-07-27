import { Label } from '@/components/ui/label';
import type { FieldLabelProps } from './types';

export const FieldLabel: React.FC<FieldLabelProps> = ({ text, htmlFor }) => {
    if (!text) {
        return null;
    }

    return (
        <Label className="text-xs font-normal" htmlFor={htmlFor}>
            {text}
        </Label>
    );
};
