/**
 * Locale code, kept as a bare `string` so this presentational component holds no
 * dependency on the shared i18n module (fleet 会議R1②). The consumer supplies
 * concrete values from its own locale module.
 */
export type LocaleCode = string;

// Endonyms are never translated (fleet 判例19) — the language's own name is the
// same in every UI locale, so this map stays hardcoded here.
const LABEL: Record<string, string> = {
  ja: '日本語',
  en: 'English',
};

export interface LanguageSwitcherProps {
  /** Resolved visible/aria label for the control (e.g. "Language"). */
  label: string;
  /** Currently selected locale. */
  locale: LocaleCode;
  /** Called with the chosen locale when the selection changes. */
  onLocaleChange: (locale: LocaleCode) => void;
  /** The selectable locales, in display order. */
  locales: readonly LocaleCode[];
}

export function LanguageSwitcher({
  label,
  locale,
  onLocaleChange,
  locales,
}: LanguageSwitcherProps) {
  // Regenerated from `.lang` / `.lang select` (#428). Below 768px the retired rule set
  // `font-size: 0` on the label to hide the text while keeping it for assistive tech.
  return (
    <label className="inline-flex items-center gap-1.75 text-xs leading-inherit text-text-muted max-md:text-zero max-md:gap-0">
      {label}
      <select
        className="text-xs leading-inherit border border-x-line-mid bg-surface-raised rounded-sm py-1.25 px-2 text-text-primary max-md:text-2xs max-md:py-1.5 max-md:px-1.75"
        value={locale}
        onChange={(e) => {
          onLocaleChange(e.target.value);
        }}
        aria-label={label}
      >
        {locales.map((code) => (
          <option key={code} value={code}>
            {LABEL[code] ?? code}
          </option>
        ))}
      </select>
    </label>
  );
}
