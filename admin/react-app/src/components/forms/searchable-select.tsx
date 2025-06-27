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

  // Check if options are grouped (has nested objects)
  const isGrouped = !Array.isArray(options) && Object.values(options).some(option => typeof option === 'object')

  // Get selected option label
  const getSelectedLabel = (): string => {
    if (!value) return ''
    
    if (Array.isArray(options)) {
      const option = options.find((opt: any) => opt.value === value || Object.keys(opt)[0] === value)
      return option ? (option.label || Object.values(option)[0]) : ''
    }
    
    if (isGrouped) {
      // Search through grouped options
      for (const [groupKey, groupOptions] of Object.entries(options)) {
        if (typeof groupOptions === 'object') {
          for (const [optionKey, optionLabel] of Object.entries(groupOptions)) {
            if (optionKey === value) {
              return optionLabel as string
            }
          }
        }
      }
    } else {
      // Simple key-value options
      return options[value] || ''
    }
    
    return ''
  }

  const selectedOption = getSelectedLabel()

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
            {isGrouped ? (
              // Render grouped options
              Object.entries(options).map(([groupKey, groupOptions]) => {
                if (typeof groupOptions === 'object') {
                  return (
                    <CommandGroup key={groupKey} heading={groupKey}>
                      {Object.entries(groupOptions).map(([optionKey, optionLabel]) => (
                        <CommandItem
                          key={optionKey}
                          value={optionKey}
                          onSelect={(currentValue) => {
                            onValueChange(currentValue === value ? "" : currentValue)
                            setOpen(false)
                          }}
                        >
                          <Check
                            className={cn(
                              "mr-2 h-4 w-4",
                              value === optionKey ? "opacity-100" : "opacity-0"
                            )}
                          />
                          {optionLabel}
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  )
                }
                return null
              })
            ) : Array.isArray(options) ? (
              // Render array options
              <CommandGroup>
                {options.map((opt: any) => {
                  const optionValue = opt.value || Object.keys(opt)[0]
                  const optionLabel = opt.label || Object.values(opt)[0]
                  return (
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
                  )
                })}
              </CommandGroup>
            ) : (
              // Render simple key-value options
              <CommandGroup>
                {Object.entries(options).map(([optionKey, optionLabel]) => (
                  <CommandItem
                    key={optionKey}
                    value={optionKey}
                    onSelect={(currentValue) => {
                      onValueChange(currentValue === value ? "" : currentValue)
                      setOpen(false)
                    }}
                  >
                    <Check
                      className={cn(
                        "mr-2 h-4 w-4",
                        value === optionKey ? "opacity-100" : "opacity-0"
                      )}
                    />
                    {optionLabel}
                  </CommandItem>
                ))}
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
} 