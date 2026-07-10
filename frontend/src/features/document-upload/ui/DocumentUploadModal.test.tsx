import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { env } from '@/shared/config/env';
import { DocumentUploadModal } from './DocumentUploadModal';

/**
 * Regression test for #137: the file hint's {{max_size_mb}} placeholder was
 * rendered literally because the modal never passed interpolation params.
 * The hint must show the configured limit (backend NENE_VAULT_MAX_FILE_SIZE_MB
 * mirror, default 20) as a real number.
 */
describe('DocumentUploadModal file hint', () => {
  it('interpolates the max upload size instead of showing the raw placeholder', () => {
    renderWithProviders(<DocumentUploadModal onClose={vi.fn()} />);

    const hint = screen.getByText(/PDF/);
    expect(hint.textContent).toContain(`${String(env.uploadMaxFileSizeMb)} MB`);
    expect(hint.textContent).not.toContain('{{');
  });

  it('defaults the mirrored limit to the backend default of 20 MB', () => {
    expect(env.uploadMaxFileSizeMb).toBe(20);
  });
});
