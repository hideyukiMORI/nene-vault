import { describe, expect, it } from 'vitest';
import { AppError, parseProblemDetails } from './errors';

const makeProblem = (type: string, status: number) => ({
  type,
  title: 'Some Error',
  status,
});

describe('AppError', () => {
  it('uses problem title as message when present', () => {
    const err = new AppError(422, makeProblem('https://example.com/problems/bad', 422));
    expect(err.message).toBe('Some Error');
  });

  it('generates a fallback message when problem is null', () => {
    const err = new AppError(503, null);
    expect(err.message).toBe('Request failed with status 503');
  });

  it('sets name to AppError', () => {
    expect(new AppError(400, null).name).toBe('AppError');
  });

  describe('isRetryable', () => {
    it('is true for status 0 (network error)', () => {
      expect(new AppError(0, null).isRetryable).toBe(true);
    });

    it('is true for 500', () => {
      expect(new AppError(500, null).isRetryable).toBe(true);
    });

    it('is true for 503', () => {
      expect(new AppError(503, null).isRetryable).toBe(true);
    });

    it('is false for 400', () => {
      expect(new AppError(400, null).isRetryable).toBe(false);
    });

    it('is false for 401', () => {
      expect(new AppError(401, null).isRetryable).toBe(false);
    });

    it('is false for 422', () => {
      expect(new AppError(422, null).isRetryable).toBe(false);
    });
  });

  describe('problemSlug', () => {
    it('extracts the last path segment from the type URI', () => {
      const err = new AppError(
        404,
        makeProblem('https://nene-vault.dev/problems/document-not-found', 404),
      );
      expect(err.problemSlug).toBe('document-not-found');
    });

    it('returns null when problem is null', () => {
      expect(new AppError(500, null).problemSlug).toBeNull();
    });

    it('handles type URI with trailing slash gracefully', () => {
      const err = new AppError(404, makeProblem('https://example.com/problems/', 404));
      // empty string after last slash
      expect(err.problemSlug).toBe('');
    });
  });
});

describe('parseProblemDetails', () => {
  it('parses a valid problem+json response', async () => {
    const body = JSON.stringify({
      type: 'https://nene-vault.dev/problems/not-found',
      title: 'Not Found',
      status: 404,
      detail: 'The resource was not found.',
    });
    const response = new Response(body, {
      status: 404,
      headers: { 'Content-Type': 'application/problem+json' },
    });

    const error = await parseProblemDetails(response);

    expect(error).toBeInstanceOf(AppError);
    expect(error.status).toBe(404);
    expect(error.problem?.title).toBe('Not Found');
    expect(error.message).toBe('Not Found');
  });

  it('returns AppError with null problem when body is not valid JSON', async () => {
    const response = new Response('not json', { status: 500 });

    const error = await parseProblemDetails(response);

    expect(error.status).toBe(500);
    expect(error.problem).toBeNull();
  });

  it('returns AppError with null problem when JSON lacks type/title', async () => {
    const response = new Response(JSON.stringify({ message: 'oops' }), { status: 500 });

    const error = await parseProblemDetails(response);

    expect(error.status).toBe(500);
    expect(error.problem).toBeNull();
  });
});
