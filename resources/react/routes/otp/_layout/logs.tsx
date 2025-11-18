import { __, sprintf } from '@wordpress/i18n'
import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/otp/_layout/logs')({
  component: RouteComponent,
})

function RouteComponent() {
  return <div>{sprintf(__('Hello "%s"!', 'wp-sms'), '/otp/_layout/logs')}</div>
}
