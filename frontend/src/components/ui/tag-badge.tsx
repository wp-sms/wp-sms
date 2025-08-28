import { Badge } from '@/components/ui/badge'
import { Sparkles, AlertTriangle, Beaker, Crown, TestTube, Clock } from 'lucide-react'

type TagBadgeProp = {
  tag: string
  className?: string
}

const tagConfig = {
  new: { label: 'New', color: 'bg-green-100 text-green-800', icon: Sparkles },
  deprecated: { label: 'Deprecated', color: 'bg-red-100 text-red-800', icon: AlertTriangle },
  beta: { label: 'Beta', color: 'bg-yellow-100 text-yellow-800', icon: Beaker },
  pro: { label: 'Pro', color: 'bg-purple-100 text-purple-800', icon: Crown },
  experimental: { label: 'Experimental', color: 'bg-orange-100 text-orange-800', icon: TestTube },
  'coming-soon': { label: 'Coming Soon', color: 'bg-blue-100 text-blue-800', icon: Clock },
}

export function TagBadge({ tag, className = '' }: TagBadgeProp) {
  const tagInfo = tagConfig[tag as keyof typeof tagConfig]

  if (!tagInfo) {
    return null
  }

  const TagIcon = tagInfo.icon

  return (
    <Badge variant="secondary" className={`${tagInfo.color} ${className}`}>
      {TagIcon && <TagIcon className="w-3 h-3 mr-1" />}
      {tagInfo.label}
    </Badge>
  )
}
