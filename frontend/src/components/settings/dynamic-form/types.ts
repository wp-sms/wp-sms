import type { GroupSchema } from '@/models/settings/types/getGroupSchema';

export type SettingsDynamicFormProps = {
  groupSchema: GroupSchema | null | undefined;
  isInitialLoading?: boolean;
  isRefreshing?: boolean;
};
