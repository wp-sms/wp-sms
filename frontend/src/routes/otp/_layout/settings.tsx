import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/otp/_layout/settings')({
  component: RouteComponent,
})

function RouteComponent() {
  return <div>Hello "/otp/_layout/settings"!</div>
}
