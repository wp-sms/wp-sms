import type { SettingsSectionTitleProps } from './types';

export const SettingsSectionTitle: React.FC<SettingsSectionTitleProps> = ({ title, subtitle }) => {
    return (
        <div className="flex flex-col gap-y-0.5">
            <h3>{title}</h3>
            <p className="text-muted-foreground text-sm">{subtitle}</p>
        </div>
    );
};
