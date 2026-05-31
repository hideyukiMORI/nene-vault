import { describe, expect, it, beforeEach, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { useContext } from 'react';
import { I18nProvider } from './i18n-context';
import { I18nContext } from './context';

const STORAGE_KEY = 'nene-vault.locale';

function LocaleProbe() {
  const ctx = useContext(I18nContext);
  if (ctx === null) throw new Error('no context');
  return (
    <button
      type="button"
      onClick={() => {
        ctx.setLocale(ctx.locale === 'ja' ? 'en' : 'ja');
      }}
    >
      {ctx.locale}
    </button>
  );
}

describe('I18nProvider <html lang> sync', () => {
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

  it('updates <html lang> when the locale changes', () => {
    localStorage.setItem(STORAGE_KEY, 'ja');
    render(
      <I18nProvider>
        <LocaleProbe />
      </I18nProvider>,
    );
    expect(document.documentElement.lang).toBe('ja');

    act(() => {
      screen.getByRole('button').click();
    });

    expect(document.documentElement.lang).toBe('en');
    expect(localStorage.getItem(STORAGE_KEY)).toBe('en');
  });
});
