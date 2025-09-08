import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/otp/_layout/authentication-channels')({
  component: RouteComponent,
})

function RouteComponent() {
  return <div>Hello "/otp/_layout/authentication-channels"!</div>
}
