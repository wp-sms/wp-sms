import { getConfig } from './api';

let cachedTimezone: string | undefined | false = false;

function getSiteTimezone(): string | undefined {
  if (cachedTimezone !== false) return cachedTimezone;

  const tz = getConfig().timezone;
  cachedTimezone = normalizeTimezone(tz);
  return cachedTimezone;
}

function normalizeTimezone(tz: string): string | undefined {
  if (!tz) return undefined;
  if (tz.includes('/')) return tz;
  if (tz === 'UTC') return 'UTC';

  const match = tz.match(/^UTC([+-]?\d+(?:\.\d+)?)$/);
  if (!match) return tz;

  const offset = parseFloat(match[1]);
  if (offset === 0) return 'UTC';

  // Intl accepts +HH:MM offset strings (ES2024+, all current browsers)
  const sign = offset >= 0 ? '+' : '-';
  const abs = Math.abs(offset);
  const hours = Math.trunc(abs);
  const minutes = Math.round((abs % 1) * 60);
  return `${sign}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

export function formatDateTime(iso: string): string {
  const tz = getSiteTimezone();
  return new Date(iso).toLocaleString(undefined, tz ? { timeZone: tz } : undefined);
}

export function formatDate(iso: string): string {
  const tz = getSiteTimezone();
  return new Date(iso).toLocaleDateString(undefined, tz ? { timeZone: tz } : undefined);
}

const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

export function formatRelativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const seconds = Math.round(diff / 1000);
  if (seconds < 60) return rtf.format(-seconds, 'second');
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return rtf.format(-minutes, 'minute');
  const hours = Math.round(minutes / 60);
  if (hours < 24) return rtf.format(-hours, 'hour');
  const days = Math.round(hours / 24);
  if (days < 30) return rtf.format(-days, 'day');
  const months = Math.round(days / 30);
  if (months < 12) return rtf.format(-months, 'month');
  return rtf.format(-Math.round(months / 12), 'year');
}

export function formatDuration(startIso: string, endIso: string): string {
  const ms = new Date(endIso).getTime() - new Date(startIso).getTime();
  if (ms < 0) return '0s';
  const totalSeconds = Math.floor(ms / 1000);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  const parts: string[] = [];
  if (days > 0) parts.push(`${days}d`);
  if (hours > 0) parts.push(`${hours}h`);
  if (minutes > 0) parts.push(`${minutes}m`);
  if (parts.length === 0) parts.push(`${seconds}s`);
  return parts.join(' ');
}

export function formatFutureRelativeTime(iso: string): string {
  const diff = new Date(iso).getTime() - Date.now();
  if (diff <= 0) return 'now';
  const seconds = Math.round(diff / 1000);
  if (seconds < 60) return `in ${seconds}s`;
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `in ${minutes}m`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `in ${hours}h`;
  const days = Math.round(hours / 24);
  return `in ${days}d`;
}
