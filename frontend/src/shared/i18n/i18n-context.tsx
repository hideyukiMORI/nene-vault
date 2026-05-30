import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { persistLocale, resolveInitialLocale, type SupportedLocale } from './locales';
import { translate, type TranslateParams } from './translate';
import { I18nContext } from './context';

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<SupportedLocale>(resolveInitialLocale);

  const setLocale = useCallback((next: SupportedLocale) => {
    persistLocale(next);
    document.documentElement.lang = next;
    setLocaleState(next);
  }, []);

  const t = useCallback(
    (key: string, params?: TranslateParams) => translate(locale, key, params),
    [locale],
  );

  const value = useMemo(() => ({ locale, setLocale, t }), [locale, setLocale, t]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}
