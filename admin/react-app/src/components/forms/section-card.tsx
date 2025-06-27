"use client"

import React from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ExternalLink, HelpCircle, Sparkles, AlertTriangle, Beaker, Crown, TestTube, Clock } from "lucide-react"

interface SchemaField {
  key: string
  type: string
  label: string
  description: string
  default: any
  groupLabel: string
  section: string | null
  options: any
  order: number
  doc: string
  showIf: { [key: string]: string } | null
  hideIf: { [key: string]: string } | null
  repeatable: boolean
  tag?: string
  readonly?: boolean
  placeholder?: string
  fieldGroups?: any[]
}

interface SchemaSection {
  id: string
  title: string
  subtitle: string
  helpUrl: string
  tag?: string
  order: number
  fields: SchemaField[]
  readonly?: boolean
  layout: string
}

interface SectionCardProps {
  section: SchemaSection
  children: React.ReactNode
}

const tagConfig = {
  new: { label: 'New', color: 'bg-green-100 text-green-800', icon: Sparkles },
  deprecated: { label: 'Deprecated', color: 'bg-red-100 text-red-800', icon: AlertTriangle },
  beta: { label: 'Beta', color: 'bg-yellow-100 text-yellow-800', icon: Beaker },
  pro: { label: 'Pro', color: 'bg-purple-100 text-purple-800', icon: Crown },
  experimental: { label: 'Experimental', color: 'bg-orange-100 text-orange-800', icon: TestTube },
  'coming-soon': { label: 'Coming Soon', color: 'bg-blue-100 text-blue-800', icon: Clock },
}

export function SectionCard({ section, children }: SectionCardProps) {
  const tagInfo = section.tag ? tagConfig[section.tag as keyof typeof tagConfig] : null
  const TagIcon = tagInfo?.icon

  const getLayoutClass = () => {
    switch (section.layout) {
      case '2-column':
        return 'grid grid-cols-1 md:grid-cols-2 gap-6'
      case '3-column':
        return 'grid grid-cols-1 md:grid-cols-3 gap-6'
      default:
        return 'space-y-6'
    }
  }

  return (
    <Card className="mb-6">
      <CardHeader>
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-2">
              <CardTitle className="text-xl">{section.title}</CardTitle>
              {tagInfo && (
                <Badge variant="secondary" className={tagInfo.color}>
                  {TagIcon && <TagIcon className="w-3 h-3 mr-1" />}
                  {tagInfo.label}
                </Badge>
              )}
            </div>
            {section.subtitle && (
              <CardDescription className="text-base">
                {section.subtitle}
              </CardDescription>
            )}
          </div>
          {section.helpUrl && (
            <Button
              variant="ghost"
              size="sm"
              asChild
              className="flex-shrink-0"
            >
              <a
                href={section.helpUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1"
              >
                <HelpCircle className="w-4 h-4" />
                <span className="hidden sm:inline">Help</span>
                <ExternalLink className="w-3 h-3" />
              </a>
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <div className={getLayoutClass()}>
          {children}
        </div>
      </CardContent>
    </Card>
  )
} 