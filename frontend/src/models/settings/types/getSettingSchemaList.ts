export type SettingSchemaListCore = Record<
    string,
    {
        name: string;
        label: string;
        icon: string;
    }
>;

export type SettingSchemaListAddons = Record<
    string,
    {
        name: string;
        label: string;
        icon: string;
    }
>;

export type SettingSchemaListIntegrations = {
    label: string;
    children: {
        [key: string]: {
            label: string;
            children: {
                [key: string]: {
                    label: string;
                    name: string;
                    icon: string;
                };
            };
        };
    };
};

export type GetSettingSchemaListResponse = {
    success: boolean;
    data: {
        addons: SettingSchemaListAddons;
        core: SettingSchemaListCore;
        integrations: SettingSchemaListIntegrations;
    };
};

export type UseGetSettingSchemaListType = {
    response: GetSettingSchemaListResponse;
};
