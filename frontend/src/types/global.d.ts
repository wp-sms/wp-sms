import type { QueryClient } from '@tanstack/react-query'

declare global {
  interface Window {
    WP_SMS_DATA?: {
      nonce: string
      restUrl: string
      frontend_build_url: string
    }

    wp: any
  }
}

interface RouterContext {
  queryClient: QueryClient
}

// TODO: Dont know the usage of these consts
// export const __ = window.wp.i18n.__
// export const _x = window.wp.i18n._x
// export const _n = window.wp.i18n._n
