import { SUPPORTED_LOCALES, type SupportedLocale } from '@/shared/i18n/locales';
import { useTranslation } from '@/shared/i18n/use-translation';

const LABEL: Record<SupportedLocale, string> = {
  ja: '日本語',
  en: 'English',
};

export function LanguageSwitcher() {
  const { locale, setLocale, t } = useTranslation();

  return (
    <label className="flex items-center gap-stack-sm font-sans text-body text-text-muted">
      {t('navigation.language')}
      <select
        className="rounded-md border border-border bg-surface-raised px-inline-sm py-stack-sm text-text-primary"
        value={locale}
        onChange={(e) => {
          setLocale(e.target.value as SupportedLocale);
        }}
        aria-label={t('navigation.language')}
      >
        {SUPPORTED_LOCALES.map((code) => (
          <option key={code} value={code}>
            {LABEL[code]}
          </option>
        ))}
      </select>
    </label>
  );
}
