interface GetLogConfigParams {
  slug: 'auth-events'
}

type FilterGroup =
  | 'time_flow'
  | 'user_role'
  | 'channel_method'
  | 'outcome'
  | 'geo_network'
  | 'security'
  | 'ops'
  | 'global'

interface BaseFilter {
  key: string
  label: string
  group: FilterGroup
}

interface DateRangeFilter extends BaseFilter {
  type: 'date-range'
  key: 'date_range'
  default: 'last_1h' | 'last_24h' | 'last_7d' | 'last_30d' | 'custom'
  presets: {
    last_1h: string
    last_24h: string
    last_7d: string
    last_30d: string
    custom: string
  }
}

interface RadioFilter extends BaseFilter {
  type: 'radio'
  options: Record<string, string>
  default?: string
}

interface SelectFilter extends BaseFilter {
  type: 'select'
  options: Record<string, string> | []
  searchable?: boolean
}

interface MultiSelectFilter extends BaseFilter {
  type: 'multi-select'
  options: Record<string, string>
}

interface CheckboxFilter extends BaseFilter {
  type: 'checkbox'
  options: Record<string, string>
}

interface ChipsFilter extends BaseFilter {
  type: 'chips'
  options: Record<string, string>
}

interface TextFilter extends BaseFilter {
  type: 'text'
  placeholder?: string
  autocomplete?: boolean
}

interface NumberFilter extends BaseFilter {
  type: 'number'
  min?: number
  max?: number
}

type LogFilter =
  | DateRangeFilter
  | RadioFilter
  | SelectFilter
  | MultiSelectFilter
  | CheckboxFilter
  | ChipsFilter
  | TextFilter
  | NumberFilter

interface GetLogConfigResponse {
  success: boolean
  data: {
    slug: string
    label: string
    description: string
    columns: {
      key: string
      label: string
      sortable: boolean
      visisble: boolean
    }[]
    filters: LogFilter[]
  }
}

interface GetLogDataParams {
  slug: 'auth-events'
  page: number
  perPage: number
  sorts?: {
    column: string
    direction: 'ASC' | 'DESC'
  }[]
}

type LogItem = {
  id: string
  event_id: string
  flow_id: string
  timestamp_utc: string
  user_id: null | string
  channel: string
  event_type: string
  result: string
  client_ip_masked: string
  geo_country: string
  wp_role: null | string
  vendor_sid: string
  vendor_status: string
  factor_id: null | string
  attempt_count: string
  retention_days: string
  user_agent: string
}

interface GetLogDataResponse {
  success: boolean
  data: {
    rows: LogItem[]
    totalCount: number
    page: number
    perPage: number
    totalPages: number
  }
}
