import { afterEach, describe, expect, it, vi } from 'vitest';
import { resolveInitialLocale, persistLocale } from './locales';

const STORAGE_KEY = 'nene-vault.locale';

afterEach(() => {
  localStorage.clear();
  vi.restoreAllMocks();
});

describe('resolveInitialLocale', () => {
  it('returns stored "ja" from localStorage', () => {
    localStorage.setItem(STORAGE_KEY, 'ja');
    expect(resolveInitialLocale()).toBe('ja');
  });

  it('returns stored "en" from localStorage', () => {
    localStorage.setItem(STORAGE_KEY, 'en');
    expect(resolveInitialLocale()).toBe('en');
  });

  it('ignores invalid stored value and falls back to navigator', () => {
    localStorage.setItem(STORAGE_KEY, 'fr');
    vi.spyOn(navigator, 'language', 'get').mockReturnValue('ja-JP');
    expect(resolveInitialLocale()).toBe('ja');
  });

  it('returns "en" when navigator.language starts with "en"', () => {
    vi.spyOn(navigator, 'language', 'get').mockReturnValue('en-US');
    expect(resolveInitialLocale()).toBe('en');
  });

  it('returns "ja" for non-english navigator.language', () => {
    vi.spyOn(navigator, 'language', 'get').mockReturnValue('fr-FR');
    expect(resolveInitialLocale()).toBe('ja');
  });

  it('returns "ja" when localStorage is empty and navigator is Japanese', () => {
    vi.spyOn(navigator, 'language', 'get').mockReturnValue('ja-JP');
    expect(resolveInitialLocale()).toBe('ja');
  });
});

describe('persistLocale', () => {
  it('writes the locale to localStorage', () => {
    persistLocale('en');
    expect(localStorage.getItem(STORAGE_KEY)).toBe('en');
  });

  it('overwrites a previously persisted locale', () => {
    persistLocale('en');
    persistLocale('ja');
    expect(localStorage.getItem(STORAGE_KEY)).toBe('ja');
  });
});
