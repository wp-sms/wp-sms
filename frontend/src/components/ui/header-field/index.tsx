import { Separator } from '../separator';
import { SimpleHtmlRenderer } from '../simple-html-renderer';
import type { HeaderFieldProps } from './types';

export const HeaderField: React.FC<HeaderFieldProps> = ({ label, description }) => {
    return (
        <div>
            <Separator className="my-4" />

            {description && <SimpleHtmlRenderer htmlContent={description} label={label} />}
        </div>
    );
};
