"use client"

import React from 'react'
import { cn } from "@/lib/utils"

interface HtmlDescriptionProps {
  content: string
  className?: string
}

export function HtmlDescription({ content, className }: HtmlDescriptionProps) {
  if (!content) return null

  return (
    <div 
      className={cn("text-sm text-muted-foreground [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-xs [&_br]:block [&_br]:mb-2", className)}
      dangerouslySetInnerHTML={{ __html: content }}
    />
  )
} 