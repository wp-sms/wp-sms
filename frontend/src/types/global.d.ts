import type { QueryClient } from '@tanstack/react-query'

export interface RouterContext {
  queryClient: QueryClient
}

// TODO: Dont know the usage of these consts
// export const __ = window.wp.i18n.__
// export const _x = window.wp.i18n._x
// export const _n = window.wp.i18n._n
