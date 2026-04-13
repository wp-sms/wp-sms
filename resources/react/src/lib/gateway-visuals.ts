import type { Gateway } from './api';

export function getGatewayColor(gateway: Gateway): string {
  return gateway.branding?.color ?? '#6b7280';
}

export function getGatewayLogo(gateway: Gateway): string | null {
  return gateway.branding?.logo_square ?? gateway.metadata?.icon ?? null;
}

export function getGatewayInitial(name: string): string {
  return name.charAt(0).toUpperCase();
}
