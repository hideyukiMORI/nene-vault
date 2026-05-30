import { describe, expect, it } from 'vitest';
import { AppError } from '@/shared/api/errors';
import { problemMessageKey, messageKeyForError } from './map-problem-details';

function makeError(slug: string, status = 400): AppError {
  return new AppError(status, {
    type: `https://nene-vault.dev/problems/${slug}`,
    title: 'Test Error',
    status,
  });
}

describe('problemMessageKey', () => {
  it.each([
    ['unauthorized', 401, 'problem.unauthorized'],
    ['forbidden', 403, 'problem.forbidden'],
    ['org-access-denied', 403, 'problem.org_access_denied'],
    ['validation-failed', 422, 'problem.validation_failed'],
    ['document-not-found', 404, 'problem.document_not_found'],
    ['organization-not-found', 404, 'problem.organization_not_found'],
    ['organization-slug-conflict', 409, 'problem.organization_slug_conflict'],
    ['mime-type-not-allowed', 415, 'problem.mime_type_not_allowed'],
    ['file-too-large', 413, 'problem.file_too_large'],
    ['duplicate-file', 409, 'problem.duplicate_file'],
    ['invalid-credentials', 401, 'problem.invalid_credentials'],
    ['internal-server-error', 500, 'problem.internal_server_error'],
  ])('maps slug "%s" to key "%s"', (slug, status, expectedKey) => {
    expect(problemMessageKey(makeError(slug, status))).toBe(expectedKey);
  });

  it('falls back to internal_server_error for 5xx with unknown slug', () => {
    expect(problemMessageKey(makeError('unknown-error', 503))).toBe(
      'problem.internal_server_error',
    );
  });

  it('falls back to validation_failed for 4xx with unknown slug', () => {
    expect(problemMessageKey(makeError('unknown-client-error', 400))).toBe(
      'problem.validation_failed',
    );
  });
});

describe('messageKeyForError', () => {
  it('returns a key for an AppError', () => {
    const err = makeError('document-not-found', 404);
    expect(messageKeyForError(err)).toBe('problem.document_not_found');
  });

  it('returns null for a plain Error', () => {
    expect(messageKeyForError(new Error('plain error'))).toBeNull();
  });

  it('returns null for a string', () => {
    expect(messageKeyForError('some string')).toBeNull();
  });

  it('returns null for null', () => {
    expect(messageKeyForError(null)).toBeNull();
  });

  it('returns null for undefined', () => {
    expect(messageKeyForError(undefined)).toBeNull();
  });
});
