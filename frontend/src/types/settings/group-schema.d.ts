import type { UseQueryOptions } from '@tanstack/react-query'

// Specific types for field rendering
type FieldValue = string | number | boolean | string[] | Record<string, unknown>[] | null | undefined

type GetGroupSchemaParams = {
  params?: Partial<{
    groupName: string
  }>
}

type FieldOption = {
  [key: string]: string | { [key: string]: string }
}

type SchemaFieldType =
  | 'select'
  | 'checkbox'
  | 'advancedselect'
  | 'text'
  | 'number'
  | 'multiselect'
  | 'repeater'
  | 'html'
  | 'tel'
  | 'countryselect'
  | 'textarea'
  | 'color'
  | 'header'
  | 'notice'
  | 'image'

type SchemaFieldLayout =
  | '2-column'
  | '1-column'
  | '3-column'
  | '4-column'
  | '5-column'
  | '6-column'
  | '7-column'
  | '8-column'
  | '9-column'
  | '10-column'
  | '11-column'
  | '12-column'

type SchemaField = {
  key: string
  type: SchemaFieldType
  label: string
  description: string
  groupLabel: string | null
  section: string | null
  options: FieldOption
  order: number
  doc: string
  tag: string | null
  showIf: { [key: string]: string } | null
  hideIf: { [key: string]: string } | null
  repeatable: boolean
  placeholder?: string
  fieldGroups?: {
    key: string
    label: string
    description: string
    order: number
    layout: SchemaFieldLayout
    fields: SchemaField[]
  }[]
  auto_save_and_refresh: boolean
  default: unknown
  hidden: boolean
  min: number | null
  max: number | null
  options_depends_on: null
  readonly: boolean
  validateCallback: Record<string, unknown> | []
  sanitizeCallback: string | null
  step: number | null
  rows: number | null
  sub_fields?: SchemaField[]
}

type SchemaSection = {
  fields: SchemaField[]
  helpUrl: string
  id: string
  layout: string
  order: number
  readOnly: boolean
  subtitle: string
  title: string
}

type GroupSchema = {
  icon: string
  label: string
  sections: SchemaSection[]
}

type GetGroupSchemaResponse = {
  data: GroupSchema | null
}

type UseGetGroupSchemaType = {
  options: Partial<UseQueryOptions<unknown, unknown, GetGroupSchemaResponse, unknown>> & GetGroupSchemaParams
  response: GetGroupSchemaResponse
}
interface GetSchemaByGroupParams {
  groupName: SettingGroupName
  include_hidden?: boolean
}

interface GetSchemaByGroupResponse {
  data: GroupSchema | null
}
