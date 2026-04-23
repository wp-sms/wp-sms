/**
 * Shared REST client for all WSMS frontends.
 *
 * Built on top of @wordpress/api-fetch — the package is externalized at build
 * time and resolved to `window.wp.apiFetch`, which WP core pre-configures
 * with the correct root URL (handling plain and pretty permalinks) and a
 * REST nonce kept in sync by the heartbeat API. Consumers must enqueue the
 * `wp-api-fetch` script as a dependency so the global is populated.
 *
 * We layer our own middlewares on top:
 *
 *   - namespace + locale: auto-prefix `wsms/v1/` and append `_locale=user`
 *   - step-up re-auth: replay a request once after `fresh_auth_required`
 *   - error normalization: surface a stable `ApiError` shape to callers
 *
 * Install is idempotent — on a page where multiple bundles import this file
 * they all share a single apiFetch instance and a single set of middlewares.
 */

import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = 'wsms/v1';
const INSTALLED_FLAG = '__wsmsMiddlewaresInstalled';

type ApiFetchWithFlag = typeof apiFetch & { [INSTALLED_FLAG]?: boolean };

export interface ApiFetchOptions {
  path?: string;
  url?: string;
  method?: string;
  data?: unknown;
  body?: BodyInit | null;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  parse?: boolean;
}

export interface ApiError {
  status: number;
  code?: string;
  error?: string;
  message?: string;
  data?: unknown;
}

export interface FreshAuthGateInfo {
  step_up_methods: string[];
  current_freshness_age: number | null;
}

type FreshAuthHandler = (info: FreshAuthGateInfo) => Promise<boolean>;

let freshAuthHandler: FreshAuthHandler | null = null;
let pendingStepUp: Promise<boolean> | null = null;

export function setFreshAuthHandler(handler: FreshAuthHandler | null): void {
  freshAuthHandler = handler;
}

function runOneStepUp(info: FreshAuthGateInfo): Promise<boolean> {
  if (!freshAuthHandler) return Promise.resolve(false);
  // Deduplicate concurrent gate triggers so only one modal is shown even if
  // several in-flight requests fail at once. A thrown handler maps to "step
  // up declined" so the singleton always resolves.
  if (pendingStepUp === null) {
    pendingStepUp = Promise.resolve()
      .then(() => freshAuthHandler!(info))
      .catch(() => false)
      .finally(() => { pendingStepUp = null; });
  }
  return pendingStepUp;
}

function install(): void {
  const instance = apiFetch as ApiFetchWithFlag;
  if (instance[INSTALLED_FLAG]) return;
  instance[INSTALLED_FLAG] = true;

  // Namespace + locale. Runs before wp-core root/nonce middlewares because
  // apiFetch.use() pushes to the front of the chain.
  apiFetch.use((options, next) => {
    let path = options.path;
    if (path && !/^https?:\/\//.test(path)) {
      path = path.replace(/^\//, '');
      if (!path.startsWith(`${NAMESPACE}/`)) {
        path = `${NAMESPACE}/${path}`;
      }
      const sep = path.includes('?') ? '&' : '?';
      path = `${path}${sep}_locale=user`;
      return next({ ...options, path });
    }
    return next(options);
  });

  // Step-up re-auth gate.
  apiFetch.use(async (options, next) => {
    try {
      return await next(options);
    } catch (error) {
      if (isFreshAuthError(error)) {
        const data = (error as { data?: Partial<FreshAuthGateInfo> }).data ?? {};
        const info: FreshAuthGateInfo = {
          step_up_methods: data.step_up_methods ?? [],
          current_freshness_age: data.current_freshness_age ?? null,
        };
        const ok = await runOneStepUp(info);
        if (ok) return next(options);
      }
      throw error;
    }
  });
}

function isFreshAuthError(error: unknown): boolean {
  if (!error || typeof error !== 'object') return false;
  const e = error as { code?: string; error?: string };
  return e.code === 'fresh_auth_required' || e.error === 'fresh_auth_required';
}

function normalizeError(raw: unknown): ApiError {
  if (raw && typeof raw === 'object') {
    const body = raw as Record<string, unknown>;
    const data = body.data as { status?: number } | undefined;
    const status = typeof data?.status === 'number' ? data.status : 0;
    return { status, ...body } as ApiError;
  }
  return { status: 0, message: String(raw) };
}

async function request<T>(options: ApiFetchOptions): Promise<T> {
  install();
  try {
    return (await apiFetch(options)) as T;
  } catch (raw) {
    throw normalizeError(raw);
  }
}

export interface RequestOptions {
  signal?: AbortSignal;
  headers?: Record<string, string>;
}

export const api = {
  get: <T>(path: string, opts?: RequestOptions) =>
    request<T>({ path, method: 'GET', signal: opts?.signal, headers: opts?.headers }),
  post: <T>(path: string, body: unknown, opts?: RequestOptions) =>
    request<T>({ path, method: 'POST', data: body, signal: opts?.signal, headers: opts?.headers }),
  put: <T>(path: string, body: unknown, opts?: RequestOptions) =>
    request<T>({ path, method: 'PUT', data: body, signal: opts?.signal, headers: opts?.headers }),
  del: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>({ path, method: 'DELETE', data: body, signal: opts?.signal, headers: opts?.headers }),
  upload: <T>(path: string, formData: FormData, opts?: RequestOptions) =>
    request<T>({ path, method: 'POST', body: formData, signal: opts?.signal, headers: opts?.headers }),
};
