import { useContext } from 'react';
import { LocaleContext, type LocaleContextValue } from './context';

export function useLocale(): LocaleContextValue {
  const ctx = useContext(LocaleContext);
  if (ctx === null) {
    throw new Error('useLocale must be used within a LocaleProvider.');
  }
  return ctx;
}
