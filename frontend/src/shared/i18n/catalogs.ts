// Catalogs are the repository-root locales/ JSON (single source of truth, ADR 0005).
// Both files share an identical key structure (enforced by `composer locales`).
import ja from '@locales/ja.json';
import en from '@locales/en.json';
import type { SupportedLocale } from './locales';

export type LocaleCatalog = typeof ja;

export const catalogs: Record<SupportedLocale, LocaleCatalog> = {
  ja,
  en,
};
