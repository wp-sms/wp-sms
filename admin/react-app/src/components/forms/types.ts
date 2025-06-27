export interface FieldOption {
  [key: string]: string | { [key: string]: string }
}

export interface SchemaField {
  key: string
  type: string
  label: string
  description: string
  default: any
  groupLabel: string
  section: string | null
  options: FieldOption | any[]
  order: number
  doc: string
  showIf: { [key: string]: string } | null
  hideIf: { [key: string]: string } | null
  repeatable: boolean
  tag?: string
  readonly?: boolean
  options_depends_on?: string
  sortable?: boolean
  placeholder?: string
  fieldGroups?: any[]
  min?: number
  max?: number
  step?: number
  rows?: number
}

export interface SchemaSection {
  id: string
  title: string
  subtitle: string
  helpUrl: string
  tag?: string
  order: number
  fields: SchemaField[]
  readonly?: boolean
  layout: string
}

export interface GroupSchema {
  label: string
  icon: string
  sections: SchemaSection[]
}

export interface DynamicFormProps {
  schema: GroupSchema | null
  savedValues: Record<string, any> | null
  loading: boolean
  error: string | null
  onSaveSuccess?: (savedKeys: string[]) => void
}

export interface FieldRendererProps {
  field: SchemaField
  value: any
  onChange: (value: any) => void
  error?: string
} 