// Locale strings come from the repository-root locales/ directory — the single
// source of truth (ADR 0005, docs/development/locale-guide.md). Do not duplicate
// strings in components; look them up by key via the useLocale() hook.
import ja from '@locales/ja.json';
import en from '@locales/en.json';

export type LocaleCode = 'ja' | 'en';

// Both files share an identical key structure (enforced by composer locales),
// so the ja catalog is a safe shape reference.
export type LocaleCatalog = typeof ja;

export const catalogs: Record<LocaleCode, LocaleCatalog> = {
  ja: ja as LocaleCatalog,
  en: en as LocaleCatalog,
};

export const DEFAULT_LOCALE: LocaleCode = 'ja';

export function resolveInitialLocale(): LocaleCode {
  const stored = localStorage.getItem('nene-vault.locale');
  if (stored === 'ja' || stored === 'en') {
    return stored;
  }
  const nav = navigator.language.toLowerCase();
  return nav.startsWith('en') ? 'en' : DEFAULT_LOCALE;
}

/**
 * Look up a dot-notation key (e.g. "auth.login.title") in a catalog.
 * Returns the key itself when missing, so a missing string is visible in the UI
 * rather than silently blank.
 */
export function lookup(catalog: LocaleCatalog, key: string): string {
  const value = key.split('.').reduce<unknown>((node, part) => {
    if (node !== null && typeof node === 'object' && part in node) {
      return (node as Record<string, unknown>)[part];
    }
    return undefined;
  }, catalog);

  return typeof value === 'string' ? value : key;
}

/** Interpolate {{name}} placeholders. */
export function interpolate(template: string, params?: Record<string, string | number>): string {
  if (!params) {
    return template;
  }
  return template.replace(/\{\{(\w+)\}\}/g, (_, name: string) =>
    name in params ? String(params[name]) : `{{${name}}}`,
  );
}
