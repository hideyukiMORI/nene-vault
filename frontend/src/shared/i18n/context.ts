import { createContext } from 'react';
import type { SupportedLocale } from './locales';
import type { TranslateParams } from './translate';

export interface I18nContextValue {
  locale: SupportedLocale;
  setLocale: (locale: SupportedLocale) => void;
  t: (key: string, params?: TranslateParams) => string;
}

export const I18nContext = createContext<I18nContextValue | null>(null);
