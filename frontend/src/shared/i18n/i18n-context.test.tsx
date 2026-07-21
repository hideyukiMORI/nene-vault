import { render, screen, fireEvent } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { I18nProvider } from './i18n-context';
import { useTranslation } from './use-translation';

const STORAGE_KEY = 'nene-vault.locale';

// Probes the shared runtime through vault's public hook: the vault-specific
// side effects (<html lang> sync + localStorage persist) must survive the
// nene2-i18n runtime 昇格 (behaviour unchanged).
function LocaleProbe() {
  const { locale, setLocale } = useTranslation();
  return (
    <button
      type="button"
      onClick={() => {
        setLocale(locale === 'ja' ? 'en' : 'ja');
      }}
    >
      {locale}
    </button>
  );
}

describe('I18nProvider <html lang> sync + persistence', () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.lang = 'ja';
  });

  afterEach(() => {
    localStorage.clear();
  });

  it('syncs <html lang> to the resolved initial locale on mount (en)', () => {
    localStorage.setItem(STORAGE_KEY, 'en');
    render(
      <I18nProvider>
        <LocaleProbe />
      </I18nProvider>,
    );
    expect(document.documentElement.lang).toBe('en');
    expect(screen.getByRole('button')).toHaveTextContent('en');
  });

  it('syncs <html lang> to ja when the initial locale is ja', () => {
    localStorage.setItem(STORAGE_KEY, 'ja');
    render(
      <I18nProvider>
        <LocaleProbe />
      </I18nProvider>,
    );
    expect(document.documentElement.lang).toBe('ja');
  });

  it('updates <html lang> and persists to localStorage when the locale changes', () => {
    localStorage.setItem(STORAGE_KEY, 'ja');
    render(
      <I18nProvider>
        <LocaleProbe />
      </I18nProvider>,
    );
    expect(document.documentElement.lang).toBe('ja');

    fireEvent.click(screen.getByRole('button'));

    expect(document.documentElement.lang).toBe('en');
    expect(localStorage.getItem(STORAGE_KEY)).toBe('en');
    expect(screen.getByRole('button')).toHaveTextContent('en');
  });
});
