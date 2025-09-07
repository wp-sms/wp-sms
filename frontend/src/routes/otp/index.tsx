import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/otp/')({
  component: RouteComponent,
})

function RouteComponent() {
  return <div>Hello "/otp/"!</div>
}
