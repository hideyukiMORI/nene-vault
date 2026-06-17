import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import type { DocumentFile } from '@/entities/document';
import { DocumentPreview } from './DocumentPreview';

function file(overrides: Partial<DocumentFile>): DocumentFile {
  return {
    url: 'blob:mock',
    blob: undefined,
    mimeType: 'image/jpeg',
    status: 'verified',
    ...overrides,
  };
}

describe('DocumentPreview', () => {
  it('renders an image with the integrity badge when verified', () => {
    const { container } = renderWithProviders(
      <DocumentPreview file={file({ status: 'verified', mimeType: 'image/jpeg' })} />,
    );
    expect(screen.getByRole('img')).toHaveAttribute('src', 'blob:mock');
    expect(container.querySelector('.badge-success')?.textContent).toContain('✓');
  });

  it('renders an iframe for PDFs', () => {
    const { container } = renderWithProviders(
      <DocumentPreview file={file({ status: 'verified', mimeType: 'application/pdf' })} />,
    );
    expect(container.querySelector('iframe.preview-frame')).toHaveAttribute('src', 'blob:mock');
  });

  it('hides the bytes and shows a danger callout on integrity mismatch', () => {
    const { container } = renderWithProviders(
      <DocumentPreview file={file({ status: 'mismatch' })} />,
    );
    expect(container.querySelector('.callout-danger')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(container.querySelector('iframe')).not.toBeInTheDocument();
  });

  it('shows a placeholder while loading', () => {
    const { container } = renderWithProviders(
      <DocumentPreview file={file({ status: 'loading', url: undefined })} />,
    );
    expect(container.querySelector('.preview-empty')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('shows a danger callout on fetch error', () => {
    const { container } = renderWithProviders(
      <DocumentPreview file={file({ status: 'error', url: undefined })} />,
    );
    expect(container.querySelector('.callout-danger')).toBeInTheDocument();
  });

  it('shows an unsupported placeholder for non-previewable verified types', () => {
    const { container } = renderWithProviders(
      <DocumentPreview file={file({ status: 'verified', mimeType: 'application/zip' })} />,
    );
    expect(container.querySelector('.preview-empty')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(container.querySelector('iframe')).not.toBeInTheDocument();
  });
});
