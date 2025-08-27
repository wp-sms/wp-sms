declare global {
  interface Window {
    wp: any;
  }
}

// Export JSX runtime functions from WordPress's React
export const jsx = window.wp.element.createElement;
export const jsxs = window.wp.element.createElement;
export const Fragment = window.wp.element.Fragment;
