import { __ } from '@wordpress/i18n'
import { AlertTriangle, Beaker, Clock, Crown, ShoppingCart, Sparkles, TestTube } from 'lucide-react'

import { Badge } from '@/components/ui/badge'

type TagBadgeProp = {
  tag: string | string[]
  className?: string
}

const tagConfig = {
  new: { label: __('New', 'wp-sms'), color: 'bg-green-100 text-green-800', icon: Sparkles },
  deprecated: { label: __('Deprecated', 'wp-sms'), color: 'bg-red-100 text-red-800', icon: AlertTriangle },
  beta: { label: __('Beta', 'wp-sms'), color: 'bg-yellow-100 text-yellow-800', icon: Beaker },
  pro: { label: __('Pro', 'wp-sms'), color: 'bg-purple-100 text-purple-800', icon: Crown },
  woocommerce: { label: __('WooCommerce', 'wp-sms'), color: 'bg-pink-100 text-pink-800', icon: ShoppingCart },
  experimental: { label: __('Experimental', 'wp-sms'), color: 'bg-orange-100 text-orange-800', icon: TestTube },
  'coming-soon': { label: __('Coming Soon', 'wp-sms'), color: 'bg-blue-100 text-blue-800', icon: Clock },
}

export function TagBadge({ tag, className = '' }: TagBadgeProp) {
  const tags = Array.isArray(tag) ? tag : [tag]

  return (
    <>
      {tags.map((tagItem, index) => {
        const tagInfo = tagConfig[tagItem as keyof typeof tagConfig]

        if (!tagInfo) {
          return null
        }

        const TagIcon = tagInfo.icon

        return (
          <Badge key={`${tagItem}-${index}`} variant="secondary" className={`${tagInfo.color} ${className}`}>
            {TagIcon && <TagIcon className="w-3 h-3 mr-1" />}
            {tagInfo.label}
          </Badge>
        )
      })}
    </>
  )
}
