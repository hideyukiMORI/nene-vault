// Catalogs are the repository-root locales/ JSON (single source of truth, ADR 0005).
// Both files share an identical key structure (enforced by `composer locales`).
import ja from '@locales/ja.json';
import en from '@locales/en.json';
import type { SupportedLocale } from './locales';

export type LocaleCatalog = typeof ja;

/** All dot-notation paths to string leaves of the catalog. */
type DotPaths<T> = {
  [K in keyof T & string]: T[K] extends string ? K : `${K}.${DotPaths<T[K]>}`;
}[keyof T & string];

/**
 * Every valid message key, derived from the repo-root JSON catalog (#166).
 * A typo'd or missing key is now a compile error (the #137 bug class), while
 * locales/*.json stays the single source of truth (ADR 0005).
 */
export type MessageKey = DotPaths<LocaleCatalog>;

/**
 * Escape hatch for keys built from backend-provided values (audit actions,
 * roles from a session blob). Compile-time checking is impossible there; the
 * runtime key-echo fallback in lookup() still applies. Greppable on purpose.
 */
export function dynamicMessageKey(key: string): MessageKey {
  return key as MessageKey;
}

export const catalogs: Record<SupportedLocale, LocaleCatalog> = {
  ja,
  en,
};
