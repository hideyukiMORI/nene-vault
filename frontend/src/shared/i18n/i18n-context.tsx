import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { persistLocale, resolveInitialLocale, type SupportedLocale } from './locales';
import { translate, type TranslateParams } from './translate';
import { I18nContext } from './context';

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<SupportedLocale>(resolveInitialLocale);

  // Keep <html lang> in sync with the active locale so native browser widgets
  // (e.g. the type="date" picker placeholder 年/月/日 vs mm/dd/yyyy) follow the
  // selected language — including on first mount, not only on toggle.
  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  const setLocale = useCallback((next: SupportedLocale) => {
    persistLocale(next);
    setLocaleState(next);
  }, []);

  const t = useCallback(
    (key: string, params?: TranslateParams) => translate(locale, key, params),
    [locale],
  );

  const value = useMemo(() => ({ locale, setLocale, t }), [locale, setLocale, t]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}
