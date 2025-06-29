"use client"

import type React from "react"
import { __ } from "@wordpress/i18n"
import { useState } from "react"
import { Eye, EyeOff, Plus, X, Upload } from "lucide-react"
import { FieldWrapper } from "./field-wrapper"
import { Input } from  "../ui/input"
import { Textarea } from "../ui/textarea"
import { Switch } from "../ui/switch"
import { Button } from "../ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "../ui/select"
import { Checkbox } from "../ui/checkbox"
import { RadioGroup, RadioGroupItem } from "../ui/radio-group"
import { Slider } from "../ui/slider"
import { Badge } from "../ui/badge"
import { Label } from "../ui/label"

// Text Input Field
interface TextFieldProps {
  label: string
  value?: string
  placeholder?: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isRequired?: boolean
  isLocked?: boolean
  onChange?: (value: string) => void
}

export function TextField({
  label,
  value,
  placeholder,
  description,
  tooltip,
  isPro,
  isRequired,
  isLocked,
  onChange,
}: TextFieldProps) {
  return (
    <FieldWrapper
      label={label}
      description={description}
      tooltip={tooltip}
      isPro={isPro}
      isRequired={isRequired}
      isLocked={isLocked}
    >
      <Input
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange?.(e.target.value)}
        disabled={isLocked}
        className="w-full"
      />
    </FieldWrapper>
  )
}

// Password Field
export function PasswordField({
  label,
  value,
  placeholder,
  description,
  tooltip,
  isPro,
  isRequired,
  isLocked,
  onChange,
}: TextFieldProps) {
  const [showPassword, setShowPassword] = useState(false)

  return (
    <FieldWrapper
      label={label}
      description={description}
      tooltip={tooltip}
      isPro={isPro}
      isRequired={isRequired}
      isLocked={isLocked}
    >
      <div className="relative">
        <Input
          type={showPassword ? "text" : "password"}
          value={value}
          placeholder={placeholder}
          onChange={(e) => onChange?.(e.target.value)}
          disabled={isLocked}
          className="w-full pr-10"
        />
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
          onClick={() => setShowPassword(!showPassword)}
          disabled={isLocked}
        >
          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </Button>
      </div>
    </FieldWrapper>
  )
}

// Number Field
interface NumberFieldProps extends Omit<TextFieldProps, "onChange"> {
  min?: number
  max?: number
  step?: number
  onChange?: (value: number) => void
}

export function NumberField({
  label,
  value,
  placeholder,
  description,
  tooltip,
  isPro,
  isRequired,
  isLocked,
  min,
  max,
  step,
  onChange,
}: NumberFieldProps) {
  return (
    <FieldWrapper
      label={label}
      description={description}
      tooltip={tooltip}
      isPro={isPro}
      isRequired={isRequired}
      isLocked={isLocked}
    >
      <Input
        type="number"
        value={value}
        placeholder={placeholder}
        min={min}
        max={max}
        step={step}
        onChange={(e) => onChange?.(Number(e.target.value))}
        disabled={isLocked}
        className="w-full"
      />
    </FieldWrapper>
  )
}

// Textarea Field
interface TextareaFieldProps extends Omit<TextFieldProps, "onChange"> {
  rows?: number
  onChange?: (value: string) => void
}

export function TextareaField({
  label,
  value,
  placeholder,
  description,
  tooltip,
  isPro,
  isRequired,
  isLocked,
  rows = 3,
  onChange,
}: TextareaFieldProps) {
  return (
    <FieldWrapper
      label={label}
      description={description}
      tooltip={tooltip}
      isPro={isPro}
      isRequired={isRequired}
      isLocked={isLocked}
    >
      <Textarea
        value={value}
        placeholder={placeholder}
        rows={rows}
        onChange={(e) => onChange?.(e.target.value)}
        disabled={isLocked}
        className="w-full resize-none"
      />
    </FieldWrapper>
  )
}

// Switch Field
interface SwitchFieldProps {
  label: string
  checked?: boolean
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  onChange?: (checked: boolean) => void
}

export function SwitchField({ label, checked, description, tooltip, isPro, isLocked, onChange }: SwitchFieldProps) {
  return (
    <FieldWrapper label={label} description={description} tooltip={tooltip} isPro={isPro} isLocked={isLocked}>
      <Switch checked={checked} onCheckedChange={onChange} disabled={isLocked} />
    </FieldWrapper>
  )
}

// Select Field
interface SelectFieldProps {
  label: string
  value?: string
  placeholder?: string
  options: { value: string; label: string }[]
  description?: string
  tooltip?: string
  isPro?: boolean
  isRequired?: boolean
  isLocked?: boolean
  onChange?: (value: string) => void
}

export function SelectField({
  label,
  value,
  placeholder,
  options,
  description,
  tooltip,
  isPro,
  isRequired,
  isLocked,
  onChange,
}: SelectFieldProps) {
  return (
    <FieldWrapper
      label={label}
      description={description}
      tooltip={tooltip}
      isPro={isPro}
      isRequired={isRequired}
      isLocked={isLocked}
    >
      <Select value={value} onValueChange={onChange} disabled={isLocked}>
        <SelectTrigger className="w-full">
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </FieldWrapper>
  )
}

// Multi-Select Field
interface MultiSelectFieldProps {
  label: string
  value?: string[]
  options: { value: string; label: string }[]
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  onChange?: (value: string[]) => void
}

export function MultiSelectField({
  label,
  value = [],
  options,
  description,
  tooltip,
  isPro,
  isLocked,
  onChange,
}: MultiSelectFieldProps) {
  const handleToggle = (optionValue: string) => {
    const newValue = value.includes(optionValue) ? value.filter((v) => v !== optionValue) : [...value, optionValue]
    onChange?.(newValue)
  }

  return (
    <FieldWrapper label={label} description={description} tooltip={tooltip} isPro={isPro} isLocked={isLocked}>
      <div className="space-y-2">
        {options.map((option) => (
          <div key={option.value} className="flex items-center space-x-2">
            <Checkbox
              id={option.value}
              checked={value.includes(option.value)}
              onCheckedChange={() => handleToggle(option.value)}
              disabled={isLocked}
            />
            <Label htmlFor={option.value} className="text-sm font-normal">
              {option.label}
            </Label>
          </div>
        ))}
      </div>
    </FieldWrapper>
  )
}

// Radio Group Field
interface RadioGroupFieldProps {
  label: string
  value?: string
  options: { value: string; label: string; description?: string }[]
  description?: string
  tooltip?: string
  isPro?: boolean
  isRequired?: boolean
  isLocked?: boolean
  onChange?: (value: string) => void
}

export function RadioGroupField({
  label,
  value,
  options,
  description,
  tooltip,
  isPro,
  isRequired,
  isLocked,
  onChange,
}: RadioGroupFieldProps) {
  return (
    <FieldWrapper
      label={label}
      description={description}
      tooltip={tooltip}
      isPro={isPro}
      isRequired={isRequired}
      isLocked={isLocked}
    >
      <RadioGroup value={value} onValueChange={onChange} disabled={isLocked}>
        {options.map((option) => (
          <div key={option.value} className="flex items-start space-x-2">
            <RadioGroupItem value={option.value} id={option.value} className="mt-1" />
            <div className="space-y-1">
              <Label htmlFor={option.value} className="text-sm font-normal">
                {option.label}
              </Label>
              {option.description && <p className="text-xs text-muted-foreground">{option.description}</p>}
            </div>
          </div>
        ))}
      </RadioGroup>
    </FieldWrapper>
  )
}

// Slider Field
interface SliderFieldProps {
  label: string
  value?: number[]
  min?: number
  max?: number
  step?: number
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  onChange?: (value: number[]) => void
}

export function SliderField({
  label,
  value = [0],
  min = 0,
  max = 100,
  step = 1,
  description,
  tooltip,
  isPro,
  isLocked,
  onChange,
}: SliderFieldProps) {
  return (
    <FieldWrapper label={label} description={description} tooltip={tooltip} isPro={isPro} isLocked={isLocked}>
      <div className="space-y-2">
        <div className="flex justify-between text-sm text-muted-foreground">
          <span>{min}</span>
          <span className="font-medium text-foreground">{value[0]}</span>
          <span>{max}</span>
        </div>
        <Slider
          value={value}
          onValueChange={onChange}
          min={min}
          max={max}
          step={step}
          disabled={isLocked}
          className="w-full"
        />
      </div>
    </FieldWrapper>
  )
}

// Tag Input Field
interface TagInputFieldProps {
  label: string
  value?: string[]
  placeholder?: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  onChange?: (value: string[]) => void
}

export function TagInputField({
  label,
  value = [],
  placeholder,
  description,
  tooltip,
  isPro,
  isLocked,
  onChange,
}: TagInputFieldProps) {
  const [inputValue, setInputValue] = useState("")

  const addTag = () => {
    if (inputValue.trim() && !value.includes(inputValue.trim())) {
      onChange?.([...value, inputValue.trim()])
      setInputValue("")
    }
  }

  const removeTag = (tagToRemove: string) => {
    onChange?.(value.filter((tag) => tag !== tagToRemove))
  }

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") {
      e.preventDefault()
      addTag()
    }
  }

  return (
    <FieldWrapper label={label} description={description} tooltip={tooltip} isPro={isPro} isLocked={isLocked}>
      <div className="space-y-2">
        <div className="flex gap-2">
          <Input
            value={inputValue}
            placeholder={placeholder}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyPress={handleKeyPress}
            disabled={isLocked}
            className="flex-1"
          />
          <Button type="button" variant="outline" size="sm" onClick={addTag} disabled={isLocked || !inputValue.trim()}>
            <Plus className="h-4 w-4" />
          </Button>
        </div>
        {value.length > 0 && (
          <div className="flex flex-wrap gap-2">
            {value.map((tag) => (
              <Badge key={tag} variant="secondary" className="flex items-center gap-1">
                {tag}
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="h-auto p-0 text-muted-foreground hover:text-foreground"
                  onClick={() => removeTag(tag)}
                  disabled={isLocked}
                >
                  <X className="h-3 w-3" />
                </Button>
              </Badge>
            ))}
          </div>
        )}
      </div>
    </FieldWrapper>
  )
}

// File Upload Field
interface FileUploadFieldProps {
  label: string
  accept?: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  onChange?: (file: File | null) => void
}

export function FileUploadField({
  label,
  accept,
  description,
  tooltip,
  isPro,
  isLocked,
  onChange,
}: FileUploadFieldProps) {
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null
    onChange?.(file)
  }

  return (
    <FieldWrapper label={label} description={description} tooltip={tooltip} isPro={isPro} isLocked={isLocked}>
      <div className="flex items-center gap-2">
        <Input
          type="file"
          accept={accept}
          onChange={handleFileChange}
          disabled={isLocked}
          className="hidden"
          id="file-upload"
        />
        <Button type="button" variant="outline" asChild disabled={isLocked}>
          <label htmlFor="file-upload" className="cursor-pointer">
            <Upload className="mr-2 h-4 w-4" />
            Choose File
          </label>
        </Button>
      </div>
    </FieldWrapper>
  )
}

// Color Picker Field
interface ColorFieldProps {
  label: string
  value?: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isLocked?: boolean
  onChange?: (value: string) => void
}

export function ColorField({
  label,
  value = "#ff6b35",
  description,
  tooltip,
  isPro,
  isLocked,
  onChange,
}: ColorFieldProps) {
  return (
    <FieldWrapper label={label} description={description} tooltip={tooltip} isPro={isPro} isLocked={isLocked}>
      <div className="flex items-center gap-2">
        <Input
          type="color"
          value={value}
          onChange={(e) => onChange?.(e.target.value)}
          disabled={isLocked}
          className="w-12 h-10 p-1 border rounded cursor-pointer"
        />
        <Input
          type="text"
          value={value}
          onChange={(e) => onChange?.(e.target.value)}
          disabled={isLocked}
          className="flex-1"
          placeholder="#ff6b35"
        />
      </div>
    </FieldWrapper>
  )
}
