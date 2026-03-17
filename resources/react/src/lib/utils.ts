import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function fmtNumber(n: number): string {
  return n.toLocaleString();
}

/** Group an array of items by a key function. */
export function groupBy<T>(items: T[], keyFn: (item: T) => string): Record<string, T[]> {
  const result: Record<string, T[]> = {};
  for (const item of items) {
    const key = keyFn(item);
    (result[key] ??= []).push(item);
  }
  return result;
}

/** Generate a unique node ID for flow steps. */
let nodeCounter = 0;
export function generateNodeId(): string {
  return `n_${Date.now().toString(36)}_${(++nodeCounter).toString(36)}_${Math.random().toString(36).slice(2, 6)}`;
}

/** Recursively merge two plain objects. Arrays are replaced, not merged. */
export function deepMerge<T extends Record<string, unknown>>(base: T, override: Partial<T>): T {
  const result = { ...base };
  for (const key of Object.keys(override) as (keyof T)[]) {
    const val = override[key];
    if (val !== undefined && typeof val === 'object' && val !== null && !Array.isArray(val)) {
      result[key] = deepMerge(
        (result[key] ?? {}) as Record<string, unknown>,
        val as Record<string, unknown>,
      ) as T[keyof T];
    } else if (val !== undefined) {
      result[key] = val as T[keyof T];
    }
  }
  return result;
}
