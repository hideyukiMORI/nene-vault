// vault is bilingual ja/en only (ADR 0005).
export type SupportedLocale = 'ja' | 'en';

export const DEFAULT_LOCALE: SupportedLocale = 'ja';

export const SUPPORTED_LOCALES: readonly SupportedLocale[] = ['ja', 'en'];

const STORAGE_KEY = 'nene-vault.locale';

export function resolveInitialLocale(): SupportedLocale {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'ja' || stored === 'en') {
      return stored;
    }
  } catch {
    // localStorage blocked (e.g. strict private mode) — fall through to navigator.
  }
  return navigator.language.toLowerCase().startsWith('en') ? 'en' : DEFAULT_LOCALE;
}

export function persistLocale(locale: SupportedLocale): void {
  try {
    localStorage.setItem(STORAGE_KEY, locale);
  } catch {
    // ignore persistence failure
  }
}
