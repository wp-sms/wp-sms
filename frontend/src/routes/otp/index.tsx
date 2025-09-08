import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/otp/')({
  beforeLoad: () => {
    throw redirect({ to: '/otp/activity' })
  },
})
