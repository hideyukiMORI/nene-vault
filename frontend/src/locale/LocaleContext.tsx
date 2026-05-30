import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { catalogs, interpolate, lookup, resolveInitialLocale, type LocaleCode } from './locales';
import { LocaleContext } from './context';

export function LocaleProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<LocaleCode>(resolveInitialLocale);

  const setLocale = useCallback((next: LocaleCode) => {
    localStorage.setItem('nene-vault.locale', next);
    document.documentElement.lang = next;
    setLocaleState(next);
  }, []);

  const t = useCallback(
    (key: string, params?: Record<string, string | number>) =>
      interpolate(lookup(catalogs[locale], key), params),
    [locale],
  );

  const value = useMemo(() => ({ locale, setLocale, t }), [locale, setLocale, t]);

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}
