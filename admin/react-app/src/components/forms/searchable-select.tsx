"use client"

import * as React from "react"
import { Check, ChevronsUpDown } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

interface FieldOption {
  [key: string]: string | { [key: string]: string }
}

interface SearchableSelectProps {
  options: FieldOption | any[]
  value: string
  onValueChange: (value: string) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  className?: string
}

export function SearchableSelect({
  options,
  value,
  onValueChange,
  placeholder = "Select an option...",
  searchPlaceholder = "Search options...",
  emptyText = "No options found.",
  className,
}: SearchableSelectProps) {
  const [open, setOpen] = React.useState(false)

  // Flatten options to handle both simple and grouped options or array
  const flattenedOptions: Record<string, string> = {}
  if (Array.isArray(options)) {
    options.forEach((opt: any) => {
      if (typeof opt === 'object' && 'value' in opt && 'label' in opt) {
        flattenedOptions[opt.value] = opt.label
      }
    })
  } else {
    Object.entries(options).forEach(([key, option]) => {
      if (typeof option === 'string') {
        flattenedOptions[key] = option
      } else if (typeof option === 'object') {
        // Handle grouped options
        Object.entries(option).forEach(([subKey, subOption]) => {
          if (typeof subOption === 'string') {
            flattenedOptions[subKey] = subOption
          }
        })
      }
    })
  }

  const selectedOption = value ? flattenedOptions[value] : null

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className={cn("w-full justify-between", className)}
        >
          {selectedOption || placeholder}
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-full p-0" align="start">
        <Command>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            <CommandGroup>
              {Object.entries(flattenedOptions).map(([optionValue, optionLabel]) => (
                <CommandItem
                  key={optionValue}
                  value={optionValue}
                  onSelect={(currentValue) => {
                    onValueChange(currentValue === value ? "" : currentValue)
                    setOpen(false)
                  }}
                >
                  <Check
                    className={cn(
                      "mr-2 h-4 w-4",
                      value === optionValue ? "opacity-100" : "opacity-0"
                    )}
                  />
                  {optionLabel}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
} 