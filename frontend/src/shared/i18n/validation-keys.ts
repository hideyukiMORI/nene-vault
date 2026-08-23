import { dynamicMessageKey, type MessageKey } from './catalogs';

/**
 * Locale keys for form validation, for use as Zod messages.
 *
 * 🔴 Why these are constants rather than string literals at each schema. A Zod message is
 * a plain `string`, so `'validation.reqiured'` type-checks and renders the raw key to the
 * operator. `satisfies MessageKey` moves that to a compile error.
 *
 * 🔴 Why not `common.required_marker`. Before #387 six fields across four screens passed
 * the *required marker* — "必須" / "Required" — as their error message. It reads
 * plausibly for an empty field and says the wrong thing for every other failure: an
 * unparseable date reported "Required". A marker is a label decoration; an error is a
 * sentence about what happened.
 */
export const VALIDATION = {
  required: 'validation.required' satisfies MessageKey,
  invalidEmail: 'validation.invalid_email' satisfies MessageKey,
  passwordTooShort: 'validation.password_too_short' satisfies MessageKey,
  invalidFormat: 'validation.invalid_format' satisfies MessageKey,
  invalidDate: 'validation.invalid_date' satisfies MessageKey,
  invalidAmount: 'validation.invalid_amount' satisfies MessageKey,
  tooSmall: 'validation.too_small' satisfies MessageKey,
  tooLarge: 'validation.too_large' satisfies MessageKey,
} as const;

/**
 * Resolve a React Hook Form field error into a displayable message.
 *
 * Returns `null` when the field is valid — the shape `FormField`'s `error` prop expects,
 * and the reason it is `string | null` rather than a boolean: a boolean cannot be rendered,
 * which is how vault came to mark fields invalid without ever saying why (#385).
 */
export function fieldErrorText(
  t: (key: MessageKey, params?: Record<string, string | number>) => string,
  error: { message?: string } | undefined,
  params?: Record<string, string | number>,
): string | null {
  return error?.message === undefined ? null : t(dynamicMessageKey(error.message), params);
}
