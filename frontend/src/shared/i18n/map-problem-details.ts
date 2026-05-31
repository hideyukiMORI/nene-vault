import { AppError } from '@/shared/api/errors';

// Map a Problem Details slug (or HTTP status fallback) to a locale key under `problem.*`.
const SLUG_TO_KEY: Record<string, string> = {
  unauthorized: 'problem.unauthorized',
  forbidden: 'problem.forbidden',
  'org-access-denied': 'problem.org_access_denied',
  'validation-failed': 'problem.validation_failed',
  'document-not-found': 'problem.document_not_found',
  'organization-not-found': 'problem.organization_not_found',
  'organization-slug-conflict': 'problem.organization_slug_conflict',
  'retention-window-active': 'problem.retention_window_active',
  'mime-type-not-allowed': 'problem.mime_type_not_allowed',
  'file-too-large': 'problem.file_too_large',
  'duplicate-file': 'problem.duplicate_file',
  'invalid-credentials': 'problem.invalid_credentials',
  'invalid-document-state': 'problem.invalid_document_state',
  'cannot-delete-self': 'problem.cannot_delete_self',
  'invalid-user-role': 'problem.invalid_user_role',
  'user-not-found': 'problem.user_not_found',
  'user-email-conflict': 'problem.user_email_conflict',
  'ocr-failed': 'problem.ocr_failed',
  'internal-server-error': 'problem.internal_server_error',
};

export function problemMessageKey(error: AppError): string {
  const slug = error.problemSlug;
  if (slug !== null && slug in SLUG_TO_KEY) {
    return SLUG_TO_KEY[slug] as string;
  }
  return error.status >= 500 ? 'problem.internal_server_error' : 'problem.validation_failed';
}

/**
 * Resolve a display message key from any thrown value. Returns null when the
 * value is not an AppError. Lets feature hooks map errors without importing
 * shared/api directly (FSD boundary).
 */
export function messageKeyForError(error: unknown): string | null {
  return error instanceof AppError ? problemMessageKey(error) : null;
}
