export function isAbortError(e: unknown): boolean {
  return e instanceof DOMException && e.name === 'AbortError';
}

export function getErrorMessage(err: unknown, fallback: string): string {
  return (err && typeof err === 'object' && 'message' in err)
    ? String((err as { message: unknown }).message)
    : fallback;
}
