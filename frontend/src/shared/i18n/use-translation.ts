import { useTranslation as useNene2Translation } from '@hideyukimori/nene2-i18n/react';
import type { MessageKey } from './catalogs';
import type { SupportedLocale } from './locales';

/** Params interpolated into a message ({{name}} → value). */
export type TranslateParams = Record<string, string | number>;

/**
 * The vault-facing translation surface. The runtime `t()` is the shared
 * nene2-i18n translator (createTranslator, nested + {{}} + key-echo — wired in
 * I18nProvider); this hook re-types its `key: string` back to vault's compile-time
 * `MessageKey` (DotPaths, #166) so a typo stays a compile error.
 */
export interface I18nContextValue {
  locale: SupportedLocale;
  setLocale: (locale: SupportedLocale) => void;
  t: (key: MessageKey, params?: TranslateParams) => string;
}

export function useTranslation(): I18nContextValue {
  const nene2 = useNene2Translation();

  return {
    locale: nene2.locale as SupportedLocale,
    setLocale: (locale) => {
      nene2.setLocale(locale);
    },
    t: (key, params) => nene2.t(key, params),
  };
}
