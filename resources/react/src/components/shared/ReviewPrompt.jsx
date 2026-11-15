import * as React from 'react'
import { __, _n, sprintf } from '@wordpress/i18n'
import { Star } from 'lucide-react'
import { Button } from '@/components/ui/button'

export const REVIEW_URL = 'https://wordpress.org/support/plugin/wp-sms/reviews/#new-post'

/**
 * A review request shown centered under the "Message Sent" confirmation, with no card around it.
 * Shown by the Send SMS page after the first successful send (and once more later if the admin asked to wait).
 * The stars and the main button do the same thing: open the WordPress.org review form.
 */
export function ReviewPrompt({ onRate, onLater }) {
  return (
    <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center">
      <p className="wsms-whitespace-nowrap wsms-text-[13.5px] wsms-text-muted-foreground">
        {__('Enjoying WSMS? Your review helps others find it.', 'wp-sms')}
      </p>
      <div className="wsms-my-4 wsms-flex wsms-justify-center wsms-gap-1.5">
        {[1, 2, 3, 4, 5].map((n) => (
          <a
            key={n}
            href={REVIEW_URL}
            target="_blank"
            rel="noopener noreferrer"
            onClick={onRate}
            aria-label={sprintf(_n('Rate %d star', 'Rate %d stars', n, 'wp-sms'), n)}
            title={sprintf(_n('Rate %d star', 'Rate %d stars', n, 'wp-sms'), n)}
            className="wsms-group wsms-inline-flex wsms-rounded-md wsms-p-0.5 wsms-transition-transform hover:wsms-scale-110 focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring"
          >
            <Star className="wsms-h-[34px] wsms-w-[34px] wsms-fill-amber-400 wsms-text-amber-400 wsms-transition-colors group-hover:wsms-fill-amber-500 group-hover:wsms-text-amber-500" />
          </a>
        ))}
      </div>
      <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-justify-center wsms-gap-2">
        <Button size="sm" asChild>
          <a href={REVIEW_URL} target="_blank" rel="noopener noreferrer" onClick={onRate}>
            {__('Rate on WordPress.org', 'wp-sms')}
          </a>
        </Button>
        <Button size="sm" variant="ghost" onClick={onLater}>
          {__('Maybe later', 'wp-sms')}
        </Button>
      </div>
    </div>
  )
}

export default ReviewPrompt
