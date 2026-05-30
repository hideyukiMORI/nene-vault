import { catalogs, type LocaleCatalog } from './catalogs';
import type { SupportedLocale } from './locales';

export type TranslateParams = Record<string, string | number>;

/** Look up a dot-notation key; returns the key itself when missing (visible, not blank). */
export function lookup(catalog: LocaleCatalog, key: string): string {
  const value = key.split('.').reduce<unknown>((node, part) => {
    if (node !== null && typeof node === 'object' && part in node) {
      return (node as Record<string, unknown>)[part];
    }
    return undefined;
  }, catalog);

  return typeof value === 'string' ? value : key;
}

export function interpolate(template: string, params?: TranslateParams): string {
  if (params === undefined) {
    return template;
  }
  return template.replace(/\{\{(\w+)\}\}/g, (_, name: string) =>
    name in params ? String(params[name]) : `{{${name}}}`,
  );
}

export function translate(locale: SupportedLocale, key: string, params?: TranslateParams): string {
  return interpolate(lookup(catalogs[locale], key), params);
}
