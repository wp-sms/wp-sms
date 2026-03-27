/**
 * Build a geoIpLookup callback for lite-phone-input.
 *
 * Uses the plugin's REST endpoint to resolve the visitor's country
 * from CDN headers (Cloudflare CF-IPCountry, CloudFront, etc.).
 *
 * @param {string} restUrl - REST API base URL ending with wsms/v1/
 * @returns {(callback: (code: string|null) => void) => void}
 */
export function createGeoIpLookup(restUrl) {
    if (!restUrl) return undefined;

    return (callback) => {
        fetch(`${restUrl}geo-country`)
            .then(r => r.ok ? r.json() : null)
            .then(data => callback(data?.country ?? null))
            .catch(() => callback(null));
    };
}
