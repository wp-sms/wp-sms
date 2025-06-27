"use client"

import * as React from "react"
import { X, ArrowUp, ArrowDown } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"
import {
  DndContext,
  closestCenter,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  useSortable,
  arrayMove,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

interface FieldOption {
  [key: string]: string | { [key: string]: string }
}

interface SearchableMultiSelectProps {
  options: FieldOption | any[]
  value: string[]
  onValueChange: (value: string[]) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  className?: string
  sortable?: boolean
}

function DraggableBadge({ item, idx, onRemove, listeners, attributes, isDragging, transform, transition }: any) {
  return (
    <Badge
      key={item.value}
      variant="secondary"
      className={`mr-1 mb-1 flex items-center cursor-move ${isDragging ? 'opacity-50' : ''}`}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      {...attributes}
      {...listeners}
    >
      {item.label}
      <button
        type="button"
        className="ml-1 ring-offset-background rounded-full outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            onRemove(item.value)
          }
        }}
        onMouseDown={(e) => {
          e.preventDefault()
          e.stopPropagation()
        }}
        onClick={() => onRemove(item.value)}
      >
        <X className="h-3 w-3 text-muted-foreground hover:text-foreground" />
      </button>
    </Badge>
  );
}

export function SearchableMultiSelect({
  options,
  value = [],
  onValueChange,
  placeholder = "Select options...",
  searchPlaceholder = "Search options...",
  emptyText = "No options found.",
  className,
  sortable = false,
}: SearchableMultiSelectProps) {
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

  const handleSelect = (optionValue: string) => {
    const newValue = value.includes(optionValue)
      ? value.filter(v => v !== optionValue)
      : [...value, optionValue]
    onValueChange(newValue)
  }

  const handleRemove = (optionValue: string) => {
    onValueChange(value.filter(v => v !== optionValue))
  }

  // DnD-kit setup
  const sensors = useSensors(useSensor(PointerSensor));

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (active.id !== over?.id) {
      const oldIndex = value.indexOf(active.id as string);
      const newIndex = value.indexOf(over?.id as string);
      if (oldIndex !== -1 && newIndex !== -1) {
        onValueChange(arrayMove(value, oldIndex, newIndex));
      }
    }
  };

  const selectedItems = value.map(v => ({
    value: v,
    label: flattenedOptions[v] || v
  }))

  return (
    <div className={cn("w-full", className)}>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            role="combobox"
            aria-expanded={open}
            className="w-full justify-between h-auto min-h-9 p-2"
          >
            <div className="flex flex-wrap gap-1 flex-1">
              {selectedItems.length > 0 ? (
                sortable ? (
                  <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={value} strategy={verticalListSortingStrategy}>
                      {selectedItems.map((item, idx) => (
                        <SortableBadge
                          key={item.value}
                          id={item.value}
                          item={item}
                          idx={idx}
                          onRemove={handleRemove}
                        />
                      ))}
                    </SortableContext>
                  </DndContext>
                ) : (
                  selectedItems.map((item, idx) => (
                    <Badge
                      key={item.value}
                      variant="secondary"
                      className="mr-1 mb-1 flex items-center"
                    >
                      {item.label}
                      <button
                        type="button"
                        className="ml-1 ring-offset-background rounded-full outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        onKeyDown={(e) => {
                          if (e.key === "Enter") {
                            handleRemove(item.value)
                          }
                        }}
                        onMouseDown={(e) => {
                          e.preventDefault()
                          e.stopPropagation()
                        }}
                        onClick={() => handleRemove(item.value)}
                      >
                        <X className="h-3 w-3 text-muted-foreground hover:text-foreground" />
                      </button>
                    </Badge>
                  ))
                )
              ) : (
                <span className="text-muted-foreground">{placeholder}</span>
              )}
            </div>
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
                    onSelect={() => handleSelect(optionValue)}
                  >
                    <div
                      className={cn(
                        "mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary",
                        value.includes(optionValue)
                          ? "bg-primary text-primary-foreground"
                          : "opacity-50 [&_svg]:invisible"
                      )}
                    >
                      {value.includes(optionValue) && (
                        <svg
                          className="h-3 w-3"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M5 13l4 4L19 7"
                          />
                        </svg>
                      )}
                    </div>
                    {optionLabel}
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  )
}

function SortableBadge({ id, item, idx, onRemove }: any) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  return (
    <span ref={setNodeRef} style={{ display: 'inline-block' }}>
      <DraggableBadge
        item={item}
        idx={idx}
        onRemove={onRemove}
        listeners={listeners}
        attributes={attributes}
        isDragging={isDragging}
        transform={transform}
        transition={transition}
      />
    </span>
  );
} 