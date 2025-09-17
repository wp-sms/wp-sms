import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/')({
  beforeLoad: () => {
    const reactStartingPoint = window.WP_SMS_DATA?.react_starting_point

    if (reactStartingPoint) {
      // Remove the # prefix if present and ensure it starts with /
      const cleanPath = reactStartingPoint.replace(/^#/, '').startsWith('/')
        ? reactStartingPoint.replace(/^#/, '')
        : `/${reactStartingPoint.replace(/^#/, '')}`

      throw redirect({ to: cleanPath })
    }

    throw redirect({ to: '/settings' })
  },
})
