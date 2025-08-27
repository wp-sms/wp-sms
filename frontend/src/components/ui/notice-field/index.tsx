import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '../alert';
import { SimpleHtmlRenderer } from '../simple-html-renderer';
import type { NoticeFieldProps } from './types';

export const NoticeField: React.FC<NoticeFieldProps> = ({ label, description }) => {
  return (
    <Alert className="border-blue-200 bg-blue-50 text-blue-800">
      <AlertCircle className="h-4 w-4 text-blue-600" />
      <AlertDescription className="text-blue-800">
        <SimpleHtmlRenderer htmlContent={description} label={label} />
      </AlertDescription>
    </Alert>
  );
};
