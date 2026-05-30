import { useLocale } from '../locale/useLocale';
import { catalogs, type LocaleCode } from '../locale/locales';

export function LanguageSwitcher() {
  const { locale, setLocale, t } = useLocale();

  const codes: LocaleCode[] = ['ja', 'en'];

  return (
    <label className="language-switcher">
      {t('navigation.language')}{' '}
      <select
        value={locale}
        onChange={(e) => setLocale(e.target.value as LocaleCode)}
        aria-label={t('navigation.language')}
      >
        {codes.map((code) => (
          <option key={code} value={code}>
            {catalogs[code]._meta.label}
          </option>
        ))}
      </select>
    </label>
  );
}
