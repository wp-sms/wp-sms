/**
 * Constructs a URL with query parameters.
 *
 * @param {string} url - The base URL.
 * @param {Record<string, any>} [params] - An object containing query parameters.
 * @returns {string} The formatted URL with encoded query parameters.
 */
export function urlWithParams(
  url: string,
  params?: Record<string, any>
): string {
  if (!params) return url; // Return base URL if no parameters are provided

  const queryString = Object.entries(params)
    .filter(
      ([_, value]) => value !== undefined && value !== null && value !== ""
    ) // Exclude undefined, null, and empty values
    .map(
      ([key, value]) =>
        `${encodeURIComponent(key)}=${encodeURIComponent(value)}`
    )
    .join("&");

  return queryString ? `${url}?${queryString}` : url; // Append query string if parameters exist
}
