"use client"
import type { ReactNode } from "react"
import { useState, useEffect } from "react"
import { Plus, Trash2, GripVertical, HelpCircle, Lock } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader } from "@/components/ui/card"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"

// @dnd-kit imports
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core"
import { arrayMove, SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from "@dnd-kit/sortable"
import { useSortable } from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"

interface RepeaterFieldProps {
  label: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  minItems?: number
  maxItems?: number
  addButtonText?: string
  children: (index: number, onUpdate: (data: any) => void, onRemove: () => void) => ReactNode
  value?: any[]
  onChange?: (value: any[]) => void
}

// Sortable Item Component
interface SortableItemProps {
  id: string
  index: number
  isLocked: boolean
  canRemove: boolean
  onRemove: () => void
  children: ReactNode
}

function SortableItem({ id, index, isLocked, canRemove, onRemove, children }: SortableItemProps) {
  const [deleteIndex, setDeleteIndex] = useState<boolean>(false)

  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: isLocked,
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  const handleConfirmDelete = () => {
    onRemove()
    setDeleteIndex(false)
  }

  return (
    <>
      <Card
        ref={setNodeRef}
        style={style}
        className={`relative transition-all duration-200 ${
          isDragging ? "opacity-50 scale-105 shadow-lg z-50" : ""
        } ${isLocked ? "cursor-not-allowed" : ""}`}
      >
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div
                {...attributes}
                {...listeners}
                className={`flex items-center justify-center w-6 h-6 rounded ${
                  !isLocked ? "cursor-grab active:cursor-grabbing hover:bg-muted" : "cursor-not-allowed opacity-50"
                }`}
                aria-label="Drag to reorder"
              >
                <GripVertical className="h-4 w-4 text-muted-foreground" />
              </div>
              <span className="text-sm font-medium">Item {index + 1}</span>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={!canRemove || isLocked}
              className="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
              onClick={() => setDeleteIndex(true)}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        </CardHeader>
        <CardContent className="pt-0">{children}</CardContent>
      </Card>

      {/* Confirmation Dialog */}
      <AlertDialog open={deleteIndex} onOpenChange={setDeleteIndex}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Item</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete Item {index + 1}? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleConfirmDelete}>Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

export function RepeaterField({
  label,
  description,
  tooltip,
  isPro = false,
  isLocked = false,
  minItems = 0,
  maxItems = 10,
  addButtonText = "Add Item",
  children,
  value = [{}],
  onChange,
}: RepeaterFieldProps) {
  const [items, setItems] = useState(() =>
    value.map((item, index) => ({
      ...item,
      id: item.id || `item-${Date.now()}-${index}`,
    })),
  )

  // Update items when value prop changes (e.g., when API data loads)
  useEffect(() => {
    setItems(
      value.map((item, index) => ({
        ...item,
        id: item.id || `item-${Date.now()}-${index}`,
      }))
    )
  }, [value])

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  )

  const addItem = () => {
    if (items.length < maxItems) {
      const newItem = { id: `item-${Date.now()}` }
      const newItems = [...items, newItem]
      setItems(newItems)
      onChange?.(newItems)
    }
  }

  const removeItem = (index: number) => {
    if (items.length > minItems) {
      const newItems = items.filter((_, i) => i !== index)
      setItems(newItems)
      onChange?.(newItems)
    }
  }

  const updateItem = (index: number, data: any) => {
    const newItems = [...items]
    newItems[index] = { ...newItems[index], ...data }
    setItems(newItems)
    onChange?.(newItems)
  }

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event

    if (active.id !== over?.id) {
      const oldIndex = items.findIndex((item) => item.id === active.id)
      const newIndex = items.findIndex((item) => item.id === over?.id)

      const newItems = arrayMove(items, oldIndex, newIndex)
      setItems(newItems)
      onChange?.(newItems)
    }
  }

  return (
    <TooltipProvider>
      <div className={`space-y-4 ${isLocked ? "opacity-60" : ""}`}>
        <div className="flex items-center gap-2">
          <Label className="text-sm font-medium leading-none">{label}</Label>

          {tooltip && (
            <Tooltip>
              <TooltipTrigger asChild>
                <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help" />
              </TooltipTrigger>
              <TooltipContent>
                <p className="max-w-xs">{tooltip}</p>
              </TooltipContent>
            </Tooltip>
          )}

          {isPro && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Badge variant="secondary" className="text-xs bg-orange-100 text-orange-800 hover:bg-orange-200">
                  <Lock className="mr-1 h-3 w-3" />
                  Pro
                </Badge>
              </TooltipTrigger>
              <TooltipContent>
                <p>This feature requires WP SMS Pro</p>
              </TooltipContent>
            </Tooltip>
          )}
        </div>

        {description && <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>}

        <div className={`space-y-3 ${isLocked ? "pointer-events-none" : ""}`}>
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
              {items.map((item, index) => (
                <SortableItem
                  key={item.id}
                  id={item.id}
                  index={index}
                  isLocked={isLocked}
                  canRemove={items.length > minItems}
                  onRemove={() => removeItem(index)}
                >
                  {children(
                    index,
                    (data) => updateItem(index, data),
                    () => removeItem(index),
                  )}
                </SortableItem>
              ))}
            </SortableContext>
          </DndContext>
        </div>

        <Button
          type="button"
          variant="outline"
          onClick={addItem}
          disabled={items.length >= maxItems || isLocked}
          className="w-full"
        >
          <Plus className="mr-2 h-4 w-4" />
          {addButtonText}
        </Button>

        {items.length >= maxItems && <p className="text-xs text-muted-foreground">Maximum {maxItems} items allowed</p>}
      </div>
    </TooltipProvider>
  )
}

// Column layout version with same @dnd-kit implementation
export function ColumnRepeaterField({
  label,
  description,
  tooltip,
  isPro = false,
  isLocked = false,
  minItems = 0,
  maxItems = 10,
  addButtonText = "Add Item",
  children,
  value = [{}],
  onChange,
}: RepeaterFieldProps) {
  const [items, setItems] = useState(() =>
    value.map((item, index) => ({
      ...item,
      id: item.id || `item-${Date.now()}-${index}`,
    })),
  )

  // Update items when value prop changes (e.g., when API data loads)
  useEffect(() => {
    setItems(
      value.map((item, index) => ({
        ...item,
        id: item.id || `item-${Date.now()}-${index}`,
      }))
    )
  }, [value])

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  )

  const addItem = () => {
    if (items.length < maxItems) {
      const newItem = { id: `item-${Date.now()}` }
      const newItems = [...items, newItem]
      setItems(newItems)
      onChange?.(newItems)
    }
  }

  const removeItem = (index: number) => {
    if (items.length > minItems) {
      const newItems = items.filter((_, i) => i !== index)
      setItems(newItems)
      onChange?.(newItems)
    }
  }

  const updateItem = (index: number, data: any) => {
    const newItems = [...items]
    newItems[index] = { ...newItems[index], ...data }
    setItems(newItems)
    onChange?.(newItems)
  }

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event

    if (active.id !== over?.id) {
      const oldIndex = items.findIndex((item) => item.id === active.id)
      const newIndex = items.findIndex((item) => item.id === over?.id)

      const newItems = arrayMove(items, oldIndex, newIndex)
      setItems(newItems)
      onChange?.(newItems)
    }
  }

  return (
    <TooltipProvider>
      <div
        className={`grid grid-cols-1 lg:grid-cols-2 gap-6 py-6 border-b border-border last:border-b-0 ${isLocked ? "opacity-60" : ""}`}
      >
        {/* Left Column - Label and Description */}
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Label className="text-sm font-medium leading-none">{label}</Label>

            {tooltip && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help" />
                </TooltipTrigger>
                <TooltipContent>
                  <p className="max-w-xs">{tooltip}</p>
                </TooltipContent>
              </Tooltip>
            )}

            {isPro && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <Badge variant="secondary" className="text-xs bg-orange-100 text-orange-800 hover:bg-orange-200">
                    <Lock className="mr-1 h-3 w-3" />
                    Pro
                  </Badge>
                </TooltipTrigger>
                <TooltipContent>
                  <p>This feature requires WP SMS Pro</p>
                </TooltipContent>
              </Tooltip>
            )}
          </div>

          {description && <p className="text-sm text-muted-foreground leading-relaxed pr-4">{description}</p>}
        </div>

        {/* Right Column - Repeater */}
        <div className={`space-y-3 ${isLocked ? "pointer-events-none" : ""}`}>
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
              {items.map((item, index) => (
                <SortableItem
                  key={item.id}
                  id={item.id}
                  index={index}
                  isLocked={isLocked}
                  canRemove={items.length > minItems}
                  onRemove={() => removeItem(index)}
                >
                  {children(
                    index,
                    (data) => updateItem(index, data),
                    () => removeItem(index),
                  )}
                </SortableItem>
              ))}
            </SortableContext>
          </DndContext>

          <Button
            type="button"
            variant="outline"
            onClick={addItem}
            disabled={items.length >= maxItems || isLocked}
            className="w-full"
          >
            <Plus className="mr-2 h-4 w-4" />
            {addButtonText}
          </Button>

          {items.length >= maxItems && (
            <p className="text-xs text-muted-foreground">Maximum {maxItems} items allowed</p>
          )}
        </div>
      </div>
    </TooltipProvider>
  )
}
