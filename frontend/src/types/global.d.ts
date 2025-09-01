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

// TODO: Dont know the usage of these consts
// export const __ = window.wp.i18n.__
// export const _x = window.wp.i18n._x
// export const _n = window.wp.i18n._n
