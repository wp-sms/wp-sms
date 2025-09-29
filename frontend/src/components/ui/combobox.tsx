'use client'

import { CheckIcon, ChevronDownIcon } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

import { cn } from '@/lib/utils'

import { Button } from './button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from './command'
import { Popover, PopoverContent, PopoverTrigger } from './popover'

export interface ComboboxOption {
  label: string
  value: string
  children?: {
    label: string
    value: string
  }[]
}

export interface ComboboxProps {
  options: ComboboxOption[]
  value?: string
  onValueChange?: (value: string) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyMessage?: string
  buttonClassName?: string
  contentClassName?: string
  disabled?: boolean
}

export function Combobox({
  options,
  value: controlledValue,
  onValueChange,
  placeholder = 'Select option...',
  searchPlaceholder = 'Search...',
  emptyMessage = 'No option found.',
  buttonClassName,
  contentClassName,
  disabled = false,
}: ComboboxProps) {
  const [open, setOpen] = useState(false)
  const [internalValue, setInternalValue] = useState('')
  const commandListRef = useRef<HTMLDivElement>(null)

  const isControlled = controlledValue !== undefined
  const value = isControlled ? controlledValue : internalValue

  const handleValueChange = (newValue: string) => {
    if (!isControlled) {
      setInternalValue(newValue)
    }
    onValueChange?.(newValue)
  }

  const findSelectedLabel = () => {
    for (const option of options) {
      if (option.value === value) {
        return option.label
      }
      if (option.children) {
        const child = option.children.find((child) => child.value === value)
        if (child) {
          return child.label
        }
      }
    }
    return null
  }

  useEffect(() => {
    if (open && value) {
      const scrollToSelected = () => {
        const listElement = commandListRef.current
        if (!listElement) return

        const selectedItem =
          listElement.querySelector(`[data-value="${value}"]`) ||
          listElement.querySelector(`[data-combobox-value="${value}"]`) ||
          listElement.querySelector(`[cmdk-item][data-value="${value}"]`)

        if (selectedItem) {
          selectedItem.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest',
          })
        }
      }

      requestAnimationFrame(() => {
        scrollToSelected()
        setTimeout(scrollToSelected, 100)
      })
    }
  }, [open, value])

  return (
    <div>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className={cn(
              "border-input cursor-pointer data-[placeholder]:text-muted-foreground [&_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-9 data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 w-full",
              buttonClassName
            )}
          >
            {value ? findSelectedLabel() : placeholder}
            <ChevronDownIcon className="ml-auto size-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent align="start" className={cn('w-full p-0', contentClassName)}>
          <Command defaultValue={value}>
            <CommandInput placeholder={searchPlaceholder} />
            <CommandList ref={commandListRef}>
              <CommandEmpty>{emptyMessage}</CommandEmpty>
              {options.map((option, index) => {
                if (option.children) {
                  return (
                    <CommandGroup key={`combobox-group-${option.value}-${index}`} heading={option.label}>
                      {option.children.map((child, j) => (
                        <CommandItem
                          key={`group-combobox-item-${child.value}-${j}`}
                          value={child.value}
                          data-combobox-value={child.value}
                          onSelect={(currentValue) => {
                            const newValue = currentValue === value ? '' : currentValue
                            handleValueChange(newValue)
                            setOpen(false)
                          }}
                        >
                          <CheckIcon
                            className={cn('mr-2 h-4 w-4', value === child.value ? 'opacity-100' : 'opacity-0')}
                          />
                          {child.label}
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  )
                }

                return (
                  <CommandGroup key={`combobox-group-${index}`}>
                    <CommandItem
                      key={`combobox-item-${option.value}-${index}`}
                      value={option.value}
                      data-combobox-value={option.value}
                      onSelect={(currentValue) => {
                        const newValue = currentValue === value ? '' : currentValue
                        handleValueChange(newValue)
                        setOpen(false)
                      }}
                    >
                      <CheckIcon className={cn('mr-2 h-4 w-4', value === option.value ? 'opacity-100' : 'opacity-0')} />
                      {option.label}
                    </CommandItem>
                  </CommandGroup>
                )
              })}
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  )
}
