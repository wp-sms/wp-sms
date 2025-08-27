import type { FieldOption } from '@/models/settings/types/getGroupSchema';

type Options = FieldOption | ({ value: string; label: string } | { [key: string]: string })[];

export function toOptions(
  data: Options
): { value: string; label: string; children?: { value: string; label: string }[] }[] {
  if (!Array.isArray(data) && Object.values(data).some((option) => typeof option === 'object')) {
    return Object.entries(data ?? {}).map(([key, value]) => {
      if (typeof value === 'string') {
        return {
          value: key,
          label: value,
        };
      }

      return {
        label: key,
        value: key,
        children: Object.entries(value).map(([k, v]) => ({
          value: k,
          label: v,
        })),
      };
    });
  }

  if (!Array.isArray(data) && Object.values(data).every((option) => typeof option === 'string')) {
    return Object.entries(data ?? {}).map(([key, value]) => {
      return {
        value: (key || value) as string,
        label: (value || key) as string,
      };
    });
  }

  if (Array.isArray(data)) {
    return data?.map((opt) => {
      const optionValue = opt.value || Object.keys(opt)[0];
      const optionLabel = opt.label || Object.values(opt)[0];

      return {
        value: optionValue,
        label: optionLabel,
      };
    });
  }

  return [];
}
