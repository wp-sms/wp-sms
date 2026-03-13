import type { ApiClient } from './api-client';
import type { CreateUserParams, CreateUserResponse, CleanupResponse } from './types';

let userCounter = 0;
let phoneCounter = 0;

/** Generate a unique e2e test email. */
export function uniqueEmail(prefix = 'user'): string {
  return `${prefix}-${Date.now()}-${++userCounter}@e2e.test`;
}

/** Generate a unique e2e test phone number (E.164 format). */
export function uniquePhone(): string {
  const suffix = String(++phoneCounter).padStart(4, '0');
  return `+1555${String(Date.now()).slice(-6)}${suffix}`;
}

/** Create a WordPress user via the mu-plugin. */
export async function createUser(
  client: ApiClient,
  params: CreateUserParams,
): Promise<CreateUserResponse> {
  const res = await client.e2e('POST', '/create-user', params);
  const data = await res.json();

  if (!data.ok) {
    throw new Error(`Failed to create user: ${data.error ?? res.statusText}`);
  }

  return data as CreateUserResponse;
}

/** Delete all e2e test users and clear related data. */
export async function cleanup(client: ApiClient): Promise<CleanupResponse> {
  const res = await client.e2e('POST', '/cleanup');
  const data = await res.json();

  if (!data.ok) {
    throw new Error(`Cleanup failed: ${data.error ?? res.statusText}`);
  }

  return data as CleanupResponse;
}
