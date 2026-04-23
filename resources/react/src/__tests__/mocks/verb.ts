import { http, type HttpHandler, type HttpResponseResolver } from 'msw';

/**
 * MSW handler factory that matches both a direct HTTP verb and the tunneled
 * POST+X-HTTP-Method-Override form that @wordpress/api-fetch emits for
 * PUT/DELETE/PATCH. Spread the result into a handler list:
 *
 *   server.use(...verb('PUT', '/endpoint', resolver));
 */
export function verb(
  method: 'PUT' | 'DELETE' | 'PATCH',
  path: string,
  resolver: HttpResponseResolver,
): HttpHandler[] {
  const lower = method.toLowerCase() as 'put' | 'delete' | 'patch';
  return [
    http[lower](path, resolver),
    http.post(path, (info) => {
      const override = info.request.headers.get('X-HTTP-Method-Override');
      if (override?.toUpperCase() !== method) return undefined;
      return resolver(info);
    }),
  ];
}
