/**
 * HTTP client with cookie jar and nonce management for WordPress REST API E2E testing.
 */
export class ApiClient {
  private cookies: Map<string, string> = new Map();
  private nonce: string | null = null;

  constructor(
    private baseUrl: string,
    private e2eSecret: string,
  ) {}

  /** Reset session state (cookies + nonce). Call between tests. */
  resetSession(): void {
    this.cookies.clear();
    this.nonce = null;
  }

  /** Make a request to the WordPress REST API (wsms/v1 namespace). */
  async api(
    method: string,
    path: string,
    body?: unknown,
    extraHeaders?: Record<string, string>,
  ): Promise<Response> {
    const url = `${this.baseUrl}/wp-json/wsms/v1${path}`;
    return this.request(method, url, body, true, extraHeaders);
  }

  /** Make a request to the E2E helper REST API (wsms-e2e/v1 namespace). */
  async e2e(
    method: string,
    path: string,
    body?: unknown,
  ): Promise<Response> {
    const url = `${this.baseUrl}/wp-json/wsms-e2e/v1${path}`;
    return this.request(method, url, body, false);
  }

  /** Fetch nonce for a specific user via the mu-plugin. */
  async fetchNonce(userId: number): Promise<string> {
    const res = await this.e2e('GET', `/nonce?user_id=${userId}`);
    const data = await res.json();
    this.nonce = data.nonce;
    return this.nonce!;
  }

  /** Set nonce directly (e.g., after login flow sets cookies). */
  setNonce(nonce: string): void {
    this.nonce = nonce;
  }

  /** Force login a user via the mu-plugin (sets auth cookies + nonce, bypasses MFA). */
  async forceLogin(userId: number): Promise<void> {
    const res = await this.e2e('POST', '/force-login', { user_id: userId });
    const data = await res.json();
    if (!data.ok) {
      throw new Error(`Force login failed: ${data.error ?? 'unknown'}`);
    }
    // The nonce from force-login is created before the auth cookie is sent,
    // so its session token is empty and won't match on subsequent requests.
    // Fetch a proper nonce with the auth cookie included.
    await this.fetchNonce(userId);
  }

  private async request(
    method: string,
    url: string,
    body?: unknown,
    includeNonce = false,
    extraHeaders?: Record<string, string>,
  ): Promise<Response> {
    const headers: Record<string, string> = {
      'X-E2E-Secret': this.e2eSecret,
      ...extraHeaders,
    };

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }

    // Send cookies.
    if (this.cookies.size > 0) {
      headers['Cookie'] = Array.from(this.cookies.entries())
        .map(([k, v]) => `${k}=${v}`)
        .join('; ');
    }

    // Include WP nonce for authenticated API requests.
    if (includeNonce && this.nonce) {
      headers['X-WP-Nonce'] = this.nonce;
    }

    const res = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      redirect: 'manual',
    });

    // Extract Set-Cookie headers.
    this.extractCookies(res);

    return res;
  }

  private extractCookies(res: Response): void {
    const setCookieHeaders = res.headers.getSetCookie?.() ?? [];

    for (const header of setCookieHeaders) {
      const match = header.match(/^([^=]+)=([^;]*)/);
      if (match) {
        const [, name, value] = match;
        if (value === 'deleted' || value === '') {
          this.cookies.delete(name);
        } else {
          this.cookies.set(name, value);
        }
      }
    }
  }
}

/** Create an ApiClient from environment variables. */
export function createClient(): ApiClient {
  const baseUrl = process.env.WSMS_E2E_BASE_URL || 'http://wsms8.local:10048';
  const secret = process.env.WSMS_E2E_SECRET || 'e2e-test-secret';
  return new ApiClient(baseUrl, secret);
}
