import { FilterIcon } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/button'
import { Combobox } from '@/components/ui/combobox'
import { Label } from '@/components/ui/label'
import { MultiSelect } from '@/components/ui/multiselect'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'

interface ActivityFiltersProps {
  filters: ReportFilter[]
  values: Record<string, string | string[] | number | null>
  onChange: (values: Record<string, string | string[] | number | null>) => void
  onApply: () => void
  onReset: () => void
}

interface BaseFilter {
  key: string
  label: string
  default?: string
}

interface DateRangeFilter extends BaseFilter {
  type: 'date-range'
  presets: Record<string, string>
}

interface RadioFilter extends BaseFilter {
  type: 'radio'
  options: Record<string, string>
}

interface SelectFilter extends BaseFilter {
  type: 'select'
  searchable?: boolean
  options: Record<string, string> | []
}

interface MultiSelectFilter extends BaseFilter {
  type: 'multi-select'
  options: Record<string, string>
}

type ReportFilter = DateRangeFilter | RadioFilter | SelectFilter | MultiSelectFilter

export function ActivityFilters({ filters, values, onChange, onApply, onReset }: ActivityFiltersProps) {
  const [open, setOpen] = useState(false)

  const handleValueChange = (key: string, value: string | string[] | number | null) => {
    onChange({
      ...values,
      [key]: value,
    })
  }

  const handleApply = () => {
    onApply()
    setOpen(false)
  }

  const handleReset = () => {
    onReset()
  }

  const renderFilter = (filter: ReportFilter) => {
    switch (filter.type) {
      case 'select': {
        if (Array.isArray(filter.options) && filter.options.length === 0) {
          return null
        }
        const selectOptions = Object.entries(filter.options as Record<string, string>).map(([value, label]) => ({
          value,
          label,
        }))
        return (
          <div key={filter.key} className="space-y-2">
            <Label htmlFor={filter.key} className="text-sm font-normal text-foreground">
              {filter.label}
            </Label>
            <Combobox
              options={selectOptions}
              value={(values[filter.key] as string) || ''}
              onValueChange={(value) => handleValueChange(filter.key, value || null)}
              placeholder={`Select ${filter.label.toLowerCase()}`}
              searchPlaceholder={`Search ${filter.label.toLowerCase()}...`}
              emptyMessage={`No ${filter.label.toLowerCase()} found.`}
            />
          </div>
        )
      }

      case 'date-range':
        return (
          <div key={filter.key} className="space-y-2">
            <Label htmlFor={filter.key} className="text-sm font-normal text-foreground">
              {filter.label}
            </Label>
            <Select
              value={(values[filter.key] as string) || filter.default}
              onValueChange={(value) => handleValueChange(filter.key, value)}
            >
              <SelectTrigger id={filter.key} className="w-full">
                <SelectValue placeholder="Select time range" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(filter.presets).map(([key, label]) => (
                  <SelectItem key={key} value={key}>
                    {label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )

      case 'radio':
        return (
          <div key={filter.key} className="space-y-2">
            <Label className="text-sm font-normal text-foreground">{filter.label}</Label>
            <RadioGroup
              value={(values[filter.key] as string) || filter.default || ''}
              onValueChange={(value) => handleValueChange(filter.key, value || null)}
              className="gap-2"
            >
              {Object.entries(filter.options).map(([key, label]) => (
                <div key={key} className="flex items-center space-x-2">
                  <RadioGroupItem value={key} id={`${filter.key}-${key}`} />
                  <Label htmlFor={`${filter.key}-${key}`} className="text-sm font-normal cursor-pointer">
                    {label}
                  </Label>
                </div>
              ))}
            </RadioGroup>
          </div>
        )

      case 'multi-select': {
        const multiSelectValues = (values[filter.key] as string[]) || []
        const multiSelectOptions = Object.entries(filter.options).map(([value, label]) => ({
          value,
          label,
        }))
        return (
          <div key={filter.key} className="space-y-2">
            <Label className="text-sm font-normal text-foreground">{filter.label}</Label>
            <MultiSelect
              options={multiSelectOptions}
              defaultValue={multiSelectValues}
              onValueChange={(newValues) => handleValueChange(filter.key, newValues.length > 0 ? newValues : null)}
              placeholder={`Select ${filter.label.toLowerCase()}`}
              className="w-full"
            />
          </div>
        )
      }

      default:
        return null
    }
  }

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <Button variant="outline">
          <FilterIcon />
          Filters
        </Button>
      </SheetTrigger>
      <SheetContent side="right" className="w-full sm:max-w-md overflow-y-auto p-0 flex flex-col">
        <SheetHeader className="border-b pb-0">
          <SheetTitle>Filters</SheetTitle>
        </SheetHeader>
        <div className="flex-1 overflow-y-auto px-4">
          <div className="space-y-4 py-4">
            {filters.map((filter) => renderFilter(filter))}
          </div>
        </div>
        <SheetFooter className="border-t flex-row gap-2 mt-auto">
          <Button variant="outline" onClick={handleReset} className="flex-1">
            Reset
          </Button>
          <Button onClick={handleApply} className="flex-1">
            Apply Filters
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
