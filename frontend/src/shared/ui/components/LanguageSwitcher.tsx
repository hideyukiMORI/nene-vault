import { SUPPORTED_LOCALES, type SupportedLocale } from '@/shared/i18n/locales';
import { useTranslation } from '@/shared/i18n/use-translation';

const LABEL: Record<SupportedLocale, string> = {
  ja: '日本語',
  en: 'English',
};

export function LanguageSwitcher() {
  const { locale, setLocale, t } = useTranslation();

  return (
    <label className="lang">
      {t('navigation.language')}
      <select
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
