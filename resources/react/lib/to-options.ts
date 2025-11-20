import type { FieldOption } from '@/types/settings/group-schema'

type Options = FieldOption | ({ value: string; label: string; icon?: string } | { [key: string]: string })[]

export function toOptions(
  data: Options
): { value: string; label: string; icon?: string; children?: { value: string; label: string; icon?: string }[] }[] {
  // Handle object with some values being objects (could have label/icon structure or nested children)
  if (!Array.isArray(data) && Object.values(data).some((option) => typeof option === 'object')) {
    return Object.entries(data ?? {}).map(([key, value]) => {
      if (typeof value === 'string') {
        return {
          value: key,
          label: value,
        }
      }

      // Check if value has label property (format: { label: string, icon?: string })
      if (typeof value === 'object' && value !== null && 'label' in value) {
        const objValue = value as { label: string; icon?: string }
        return {
          value: key,
          label: objValue.label,
          icon: objValue.icon,
        }
      }

      // Otherwise, treat as nested children
      return {
        label: key,
        value: key,
        children: Object.entries(value).map(([k, v]) => {
          if (typeof v === 'object' && v !== null && 'label' in v) {
            const childValue = v as { label: string; icon?: string }
            return {
              value: k,
              label: childValue.label,
              icon: childValue.icon,
            }
          }
          return {
            value: k,
            label: v as string,
          }
        }),
      }
    })
  }

  // Handle object with all string values
  if (!Array.isArray(data) && Object.values(data).every((option) => typeof option === 'string')) {
    return Object.entries(data ?? {}).map(([key, value]) => {
      return {
        value: (key || value) as string,
        label: (value || key) as string,
      }
    })
  }

  // Handle array format
  if (Array.isArray(data)) {
    return data?.map((opt) => {
      const optionValue = opt.value || Object.keys(opt)[0]
      const optionLabel = opt.label || Object.values(opt)[0]
      const optionIcon = 'icon' in opt ? opt.icon : undefined

      return {
        value: optionValue,
        label: optionLabel,
        icon: optionIcon,
      }
    })
  }

  return []
}
