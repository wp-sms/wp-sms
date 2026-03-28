declare const wpSmsSettings: { isRtl?: boolean };

export const isRtl =
  typeof wpSmsSettings !== 'undefined'
    ? Boolean(wpSmsSettings.isRtl)
    : document.documentElement.dir === 'rtl';

export function useIsRtl(): boolean {
  return isRtl;
}
