import { FieldLabel } from '@/components/form/label';
import DOMPurify from 'dompurify';
import type { SimpleHtmlRendererProps } from './types';

export const SimpleHtmlRenderer: React.FC<SimpleHtmlRendererProps> = ({ htmlContent, label, name }) => {
    const sanitizedHTML = DOMPurify.sanitize(htmlContent);

    return (
        <div className="flex flex-col gap-y-1.5">
            <FieldLabel text={label} htmlFor={name} />
            <div dangerouslySetInnerHTML={{ __html: sanitizedHTML }} />
        </div>
    );
};
