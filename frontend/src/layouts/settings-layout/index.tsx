import { SettingsSidebar } from './sidebar';
import type { SettingsLayoutProps } from './types';

import { SettingsHeader } from './header';

export const SettingsLayout: React.FC<SettingsLayoutProps> = ({ children }) => {
    return (
        <div className="wrap flex w-full min-h-screen">
            <SettingsSidebar />

            <div className="flex-1 bg-white">
                <SettingsHeader />

                <main>{children}</main>
            </div>
        </div>
    );
};
