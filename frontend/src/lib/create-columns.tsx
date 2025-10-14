import { type ColumnDef, type VisibilityState } from '@tanstack/react-table'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'

import { Button } from '@/components/ui/button'

interface ColumnConfig {
  key: string
  label: string
  sortable: boolean
  visisble: boolean
}

export function createColumnsFromConfig<TData extends Record<string, unknown>>(
  columns: ColumnConfig[]
): ColumnDef<TData>[] {
  return columns.map((col) => ({
    accessorKey: col.key,
    header: col.sortable
      ? ({ column }) => {
          const sorted = column.getIsSorted()
          return (
            <Button
              variant="ghost"
              onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
              className="!ps-0"
            >
              {col.label}
              {sorted === 'asc' ? <ArrowUp /> : sorted === 'desc' ? <ArrowDown /> : <ArrowUpDown />}
            </Button>
          )
        }
      : col.label,
    enableSorting: col.sortable,
    enableHiding: col.visisble,
    // enableHiding: true,
  }))
}

export function getInitialColumnVisibility(columns: ColumnConfig[]): VisibilityState {
  const visibility = columns.reduce((acc, col) => {
    // Only set to false if explicitly false, otherwise default to true
    if (col.visisble === false) {
      acc[col.key] = false
    }
    return acc
  }, {} as VisibilityState)

  return visibility
}
