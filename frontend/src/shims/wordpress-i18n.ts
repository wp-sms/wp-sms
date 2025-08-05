declare global {
  interface Window {
    wp: any;
  }
}

export const __ = window.wp.i18n.__;
export const _x = window.wp.i18n._x;
export const _n = window.wp.i18n._n;
// Add more if needed 