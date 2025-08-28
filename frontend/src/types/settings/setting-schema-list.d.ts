type SettingSchemaListCore = Record<
  string,
  {
    name: string
    label: string
    icon: string
  }
>

type SettingSchemaListAddons = Record<
  string,
  {
    name: string
    label: string
    icon: string
  }
>

type SettingSchemaListIntegrations = {
  label: string
  children: {
    [key: string]: {
      label: string
      children: {
        [key: string]: {
          label: string
          name: string
          icon: string
        }
      }
    }
  }
}

type GetSettingSchemaListResponse = {
  success: boolean
  data: {
    addons: SettingSchemaListAddons
    core: SettingSchemaListCore
    integrations: SettingSchemaListIntegrations
  }
}

type UseGetSettingSchemaListType = {
  response: GetSettingSchemaListResponse
}
