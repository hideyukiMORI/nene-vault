import type { SupportedLocale } from '@/shared/i18n/locales';

const EM_DASH = '—';

const INTL_LOCALE: Record<SupportedLocale, string> = {
  ja: 'ja-JP',
  en: 'en-US',
};

/**
 * Format an integer-yen amount as currency for the active locale.
 * JPY has no minor unit, so `amount_cents` is whole yen (see naming-conventions).
 * Returns an em dash for null (document carries no monetary value).
 */
export function formatJpy(cents: number | null, locale: SupportedLocale): string {
  if (cents === null) {
    return EM_DASH;
  }

  return new Intl.NumberFormat(INTL_LOCALE[locale], {
    style: 'currency',
    currency: 'JPY',
  }).format(cents);
}

/**
 * Format an ISO date string (YYYY-MM-DD) for display. The statutory date itself
 * is locale-neutral (ISO), so this returns it verbatim, with an em dash for null.
 */
export function formatDate(date: string | null): string {
  return date ?? EM_DASH;
}

/**
 * Format an ISO 8601 timestamp as a locale-aware date-time for display.
 * Falls back to a trimmed ISO string if the value cannot be parsed.
 */
export function formatDateTime(iso: string | null | undefined, locale: SupportedLocale): string {
  if (iso === null || iso === undefined || iso === '') {
    return EM_DASH;
  }

  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) {
    // Defensive fallback: show the raw value trimmed to minutes.
    return iso.slice(0, 16).replace('T', ' ');
  }

  return new Intl.DateTimeFormat(INTL_LOCALE[locale], {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed);
}
