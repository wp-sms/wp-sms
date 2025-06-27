import React from 'react'
import { Separator } from "@/components/ui/separator"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { AlertCircle } from "lucide-react"
import { HtmlDescription } from "../html-description"
import { FieldRendererProps } from '../types'

export function HeaderField({ field }: FieldRendererProps) {
  const { label, description } = field

  return (
    <div className="space-y-2">
      <Separator className="my-4" />
      <h3 className="text-lg font-semibold">{label}</h3>
      {description && (
        <HtmlDescription content={description} />
      )}
    </div>
  )
}

export function NoticeField({ field }: FieldRendererProps) {
  const { label, description } = field

  return (
    <Alert className="border-blue-200 bg-blue-50 text-blue-800">
      <AlertCircle className="h-4 w-4 text-blue-600" />
      <AlertDescription className="text-blue-800">
        <div className="font-medium">{label}</div>
        {description && (
          <div className="mt-1 text-sm" dangerouslySetInnerHTML={{ __html: description }} />
        )}
      </AlertDescription>
    </Alert>
  )
}

export function HtmlField({ field, value }: FieldRendererProps) {
  const { key, label, description, tag } = field

  return (
    <div className="space-y-2">
      {label && (
        <div className="flex items-center gap-2">
          <h3 className="text-lg font-semibold">{label}</h3>
          {tag && <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">{tag}</span>}
        </div>
      )}
      <div 
        className="[&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-xs [&_br]:block [&_br]:mb-2"
        dangerouslySetInnerHTML={{ __html: value || '' }}
      />
      {description && (
        <HtmlDescription content={description} />
      )}
    </div>
  )
} 