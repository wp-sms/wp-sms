import { SettingsSidebar } from './sidebar';
import type { SettingsLayoutProps } from './types';

export const SettingsLayout: React.FC<SettingsLayoutProps> = ({ children }) => {
    return (
        <div className="flex h-full">
            <SettingsSidebar />
            <div className="flex-1">{children}</div>
        </div>
    );
};
