import { api } from './rest-client';

/**
 * Build a geoIpLookup callback for lite-phone-input.
 *
 * Resolves the visitor's country from the plugin's `geo-country` REST endpoint
 * (which reads CDN headers like Cloudflare's `CF-IPCountry`). Returns null on
 * any failure so the phone input falls back to its configured default country.
 */
export function createGeoIpLookup(): (callback: (code: string | null) => void) => void {
  return (callback) => {
    api.get<{ country?: string | null }>('geo-country')
      .then((data) => callback(data?.country ?? null))
      .catch(() => callback(null));
  };
}
