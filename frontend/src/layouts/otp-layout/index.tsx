import { OtpSidebar } from './sidebar';
import type { OtpLayoutProps } from './types';
import { OtpHeader } from './header';

export const OtpLayout: React.FC<OtpLayoutProps> = ({ children }) => {
    return (
        <div className="wrap flex w-full min-h-screen">
            <OtpSidebar />

            <div className="flex-1 bg-white">
                <OtpHeader />

                <main>{children}</main>
            </div>
        </div>
    );
};
