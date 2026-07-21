import { I18nProvider as Nene2I18nProvider, useTranslation } from '@hideyukimori/nene2-i18n/react';
import { useEffect, useState, type ReactNode } from 'react';
import { catalogs } from './catalogs';
import { persistLocale, resolveInitialLocale, type SupportedLocale } from './locales';

// vault runtime options for the shared translator (nene2-i18n createTranslator):
// nested dot-path catalog, {{name}} interpolation, and a visible key-echo
// fallback for dynamic keys (I18N-22 — never blank, never throw at runtime).
const RUNTIME_OPTIONS = {
  catalogShape: 'nested',
  interpolation: 'double',
  onMissing: 'key-echo',
} as const;

/**
 * Inside the shared provider: mirror the active locale onto localStorage and
 * `<html lang>`. The `lang` sync drives the native date-picker language (年/月/日
 * vs mm/dd/yyyy) — a vault-specific requirement the controlled-prop provider
 * delegates to the app, injected here as a side effect on locale change.
 */
function LocaleSideEffects({ children }: { children: ReactNode }) {
  const { locale } = useTranslation();

  useEffect(() => {
    persistLocale(locale as SupportedLocale);
    document.documentElement.lang = locale;
  }, [locale]);

  return <>{children}</>;
}

export function I18nProvider({ children }: { children: ReactNode }) {
  // Seed the initial locale from storage → navigator; nene2's provider owns the
  // active-locale state and setLocale thereafter.
  const [initialLocale] = useState<SupportedLocale>(resolveInitialLocale);

  return (
    <Nene2I18nProvider catalogs={catalogs} locale={initialLocale} options={RUNTIME_OPTIONS}>
      <LocaleSideEffects>{children}</LocaleSideEffects>
    </Nene2I18nProvider>
  );
}
