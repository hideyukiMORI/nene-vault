import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { ForbiddenPage } from './ForbiddenPage';

describe('ForbiddenPage', () => {
  it('renders a forbidden message', () => {
    renderWithProviders(<ForbiddenPage />);
    // The page renders the problem.forbidden locale key text
    const el = screen.getByText(/permission/i);
    expect(el).toBeInTheDocument();
  });
});
