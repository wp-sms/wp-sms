export function isAbortError(e: unknown): boolean {
  return e instanceof DOMException && e.name === 'AbortError';
}

export function getErrorMessage(err: unknown, fallback: string): string {
  return (err && typeof err === 'object' && 'message' in err)
    ? String((err as { message: unknown }).message)
    : fallback;
}

export function getApiErrorMessage(err: unknown, fallback: string): string {
  if (err && typeof err === 'object' && 'errors' in err) {
    const errors = (err as { errors?: unknown }).errors;
    if (Array.isArray(errors) && errors.length > 0) {
      return String(errors[0]);
    }
  }
  return getErrorMessage(err, fallback);
}
