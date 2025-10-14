import { type ColumnDef } from '@tanstack/react-table'

interface ColumnConfig {
  key: string
  label: string
  sortable: boolean
  visisble: boolean
}

export function createColumnsFromConfig<TData extends Record<string, unknown>>(
  columns: ColumnConfig[]
): ColumnDef<TData>[] {
  return columns
    .filter((col) => col.visisble)
    .map((col) => ({
      accessorKey: col.key,
      header: col.label,
      enableSorting: col.sortable,
    }))
}
