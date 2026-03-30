/**
 * Drift-prevention test: ensures frontend DEFAULTS keys/types match
 * the PHP SettingsRepository::DEFAULTS snapshot exported by PHPUnit.
 *
 * Run `composer test -- --filter=testExportDefaultsSnapshot` first to regenerate
 * the snapshot, then `npm test` to verify sync.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { DEFAULTS } from '../../lib/constants';

function getTypeShape(obj: unknown): unknown {
  if (obj === null) return 'null';
  if (Array.isArray(obj)) {
    if (obj.length === 0) return 'array:empty';
    return ['array', ...obj.map(getTypeShape)];
  }
  if (typeof obj === 'object') {
    const shape: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(obj as Record<string, unknown>)) {
      shape[k] = getTypeShape(v);
    }
    return shape;
  }
  return typeof obj;
}

describe('Settings defaults PHP/TS sync', () => {
  const snapshotPath = resolve(__dirname, '../../../../../tests/snapshots/settings-defaults.json');

  let phpDefaults: Record<string, unknown>;

  try {
    phpDefaults = JSON.parse(readFileSync(snapshotPath, 'utf-8'));
  } catch {
    phpDefaults = {};
  }

  it('snapshot file exists (run `composer test -- --filter=testExportDefaultsSnapshot` to generate)', () => {
    expect(Object.keys(phpDefaults).length).toBeGreaterThan(0);
  });

  it('frontend DEFAULTS has all PHP keys', () => {
    const phpKeys = Object.keys(phpDefaults).sort();
    const tsKeys = Object.keys(DEFAULTS).sort();

    const missingInTs = phpKeys.filter((k) => !tsKeys.includes(k));
    const extraInTs = tsKeys.filter((k) => !phpKeys.includes(k));

    expect(missingInTs).toEqual([]);
    expect(extraInTs).toEqual([]);
  });

  it('frontend DEFAULTS key types match PHP', () => {
    const phpShape = getTypeShape(phpDefaults);
    const tsShape = getTypeShape(DEFAULTS);

    expect(tsShape).toEqual(phpShape);
  });
});
