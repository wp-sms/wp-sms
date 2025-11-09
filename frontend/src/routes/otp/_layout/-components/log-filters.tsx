import { FilterIcon, XIcon } from 'lucide-react'
import { useState } from 'react'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { MultiSelect } from '@/components/ui/multiselect'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'

interface LogFiltersProps {
  filters: LogFilter[]
  values: Record<string, string | string[] | number | null>
  onChange: (values: Record<string, string | string[] | number | null>) => void
  onApply: () => void
  onReset: () => void
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

const groupLabels: Record<FilterGroup, string> = {
  time_flow: 'Time Flow',
  user_role: 'User Role',
  channel_method: 'Channel Method',
  outcome: 'Outcome',
  geo_network: 'Geo Network',
  security: 'Security',
  ops: 'Ops',
  global: 'Global',
}

export function LogFilters({ filters, values, onChange, onApply, onReset }: LogFiltersProps) {
  const [open, setOpen] = useState(false)

  const handleValueChange = (key: string, value: string | string[] | number | null) => {
    onChange({
      ...values,
      [key]: value,
    })
  }

  const handleCheckboxToggle = (filterKey: string, optionKey: string) => {
    const currentValues = (values[filterKey] as string[]) || []
    const newValues = currentValues.includes(optionKey)
      ? currentValues.filter((v) => v !== optionKey)
      : [...currentValues, optionKey]

    handleValueChange(filterKey, newValues.length > 0 ? newValues : null)
  }

  const handleApply = () => {
    onApply()
    setOpen(false)
  }

  const handleReset = () => {
    onReset()
  }

  const renderFilter = (filter: LogFilter) => {
    switch (filter.type) {
      case 'text':
        return (
          <div key={filter.key} className="space-y-2">
            <Label htmlFor={filter.key} className="text-sm font-normal text-foreground">
              {filter.label}
            </Label>
            <Input
              id={filter.key}
              type="text"
              placeholder={filter.placeholder}
              value={(values[filter.key] as string) || ''}
              onChange={(e) => handleValueChange(filter.key, e.target.value || null)}
              className="w-full"
            />
          </div>
        )

      case 'number':
        return (
          <div key={filter.key} className="space-y-2">
            <Label htmlFor={filter.key} className="text-sm font-normal text-foreground">
              {filter.label}
            </Label>
            <Input
              id={filter.key}
              type="number"
              min={filter.min}
              max={filter.max}
              value={(values[filter.key] as number) || ''}
              onChange={(e) => handleValueChange(filter.key, e.target.value ? Number(e.target.value) : null)}
              className="w-full"
            />
          </div>
        )

      case 'select':
        if (Array.isArray(filter.options) && filter.options.length === 0) {
          return null
        }
        return (
          <div key={filter.key} className="space-y-2">
            <Label htmlFor={filter.key} className="text-sm font-normal text-foreground">
              {filter.label}
            </Label>
            <Select
              value={(values[filter.key] as string) || ''}
              onValueChange={(value) => handleValueChange(filter.key, value || null)}
            >
              <SelectTrigger id={filter.key} className="w-full">
                <SelectValue placeholder={`Select ${filter.label.toLowerCase()}`} />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(filter.options as Record<string, string>).map(([key, label]) => (
                  <SelectItem key={key} value={key}>
                    {label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )

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

      case 'checkbox': {
        const checkboxValues = (values[filter.key] as string[]) || []
        return (
          <div key={filter.key} className="space-y-2">
            <Label className="text-sm font-normal text-foreground">{filter.label}</Label>
            <div className="space-y-2">
              {Object.entries(filter.options).map(([key, label]) => (
                <div key={key} className="flex items-center space-x-2">
                  <Checkbox
                    id={`${filter.key}-${key}`}
                    checked={checkboxValues.includes(key)}
                    onCheckedChange={() => handleCheckboxToggle(filter.key, key)}
                  />
                  <Label htmlFor={`${filter.key}-${key}`} className="text-sm font-normal cursor-pointer">
                    {label}
                  </Label>
                </div>
              ))}
            </div>
          </div>
        )
      }

      case 'chips': {
        const chipValues = (values[filter.key] as string[]) || []
        return (
          <div key={filter.key} className="space-y-2">
            <Label className="text-sm font-normal text-foreground">{filter.label}</Label>
            <div className="flex flex-wrap gap-2">
              {Object.entries(filter.options).map(([key, label]) => {
                const isSelected = chipValues.includes(key)
                return (
                  <Badge
                    key={key}
                    variant={isSelected ? 'default' : 'outline'}
                    className="cursor-pointer hover:bg-primary/90 hover:text-primary-foreground transition-colors"
                    onClick={() => {
                      const newValues = isSelected ? chipValues.filter((v) => v !== key) : [...chipValues, key]
                      handleValueChange(filter.key, newValues.length > 0 ? newValues : null)
                    }}
                  >
                    {label}
                    {isSelected && <XIcon className="ml-1 size-3" />}
                  </Badge>
                )
              })}
            </div>
          </div>
        )
      }

      default:
        return null
    }
  }

  const groupedFilters = filters.reduce(
    (acc, filter) => {
      if (!acc[filter.group]) {
        acc[filter.group] = []
      }
      acc[filter.group].push(filter)
      return acc
    },
    {} as Record<FilterGroup, LogFilter[]>
  )

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
          <div className="space-y-6">
            {Object.entries(groupedFilters).map(([group, groupFilters], index) => (
              <div key={group}>
                {index > 0 && <Separator className="mb-4" />}
                <div className="space-y-4">
                  <h3 className="text-sm font-semibold text-foreground m-0">{groupLabels[group as FilterGroup]}</h3>
                  <div className="space-y-4">{groupFilters.map((filter) => renderFilter(filter))}</div>
                </div>
              </div>
            ))}
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
