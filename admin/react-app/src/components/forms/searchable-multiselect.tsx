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

  // Check if options are grouped (has nested objects)
  const isGrouped = !Array.isArray(options) && Object.values(options).some(option => typeof option === 'object')

  // Get option label by value
  const getOptionLabel = (optionValue: string): string => {
    if (Array.isArray(options)) {
      const option = options.find((opt: any) => opt.value === optionValue || Object.keys(opt)[0] === optionValue)
      return option ? (option.label || Object.values(option)[0]) : optionValue
    }
    
    if (isGrouped) {
      // Search through grouped options
      for (const [groupKey, groupOptions] of Object.entries(options)) {
        if (typeof groupOptions === 'object') {
          for (const [key, label] of Object.entries(groupOptions)) {
            if (key === optionValue) {
              return label as string
            }
          }
        }
      }
    } else {
      // Simple key-value options
      return options[optionValue] || optionValue
    }
    
    return optionValue
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
    label: getOptionLabel(v)
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
              {isGrouped ? (
                // Render grouped options
                Object.entries(options).map(([groupKey, groupOptions]) => {
                  if (typeof groupOptions === 'object') {
                    return (
                      <CommandGroup key={groupKey} heading={groupKey}>
                        {Object.entries(groupOptions).map(([optionKey, optionLabel]) => (
                          <CommandItem
                            key={optionKey}
                            onSelect={() => handleSelect(optionKey)}
                          >
                            <div
                              className={cn(
                                "mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary",
                                value.includes(optionKey)
                                  ? "bg-primary text-primary-foreground"
                                  : "opacity-50 [&_svg]:invisible"
                              )}
                            >
                              {value.includes(optionKey) && (
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
                    )
                  })}
                </CommandGroup>
              ) : (
                // Render simple key-value options
                <CommandGroup>
                  {Object.entries(options).map(([optionKey, optionLabel]) => (
                    <CommandItem
                      key={optionKey}
                      onSelect={() => handleSelect(optionKey)}
                    >
                      <div
                        className={cn(
                          "mr-2 flex h-4 w-4 items-center justify-center rounded-sm border border-primary",
                          value.includes(optionKey)
                            ? "bg-primary text-primary-foreground"
                            : "opacity-50 [&_svg]:invisible"
                        )}
                      >
                        {value.includes(optionKey) && (
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
              )}
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